<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerTree
{
    /**
     * Every user in the subtree rooted at $rootId, including the root.
     * One recursive CTE for ids (works on MariaDB 10.2+ and SQLite), then hydrate.
     */
    public function subtreeUsers(int $rootId): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE subtree AS (
                SELECT id FROM users WHERE id = ?
                UNION ALL
                SELECT u.id FROM users u INNER JOIN subtree s ON u.parent_id = s.id
            )
            SELECT id FROM subtree
        SQL, [$rootId]);

        return User::whereIn('id', array_column($rows, 'id'))->get();
    }

    /**
     * Nested node for one root: ['user' => User, 'children' => [...], 'descendants_count' => int].
     */
    public function subtree(User $root): array
    {
        $users = $this->subtreeUsers($root->id);
        $children = $this->buildNodes($users, $root->id);

        return [
            'user' => $root,
            'children' => $children,
            'descendants_count' => collect($children)->sum(fn ($c) => 1 + $c['descendants_count']),
        ];
    }

    /**
     * All top-level sellers, each as a nested node (admins are not in the network).
     *
     * @return array<int, array>
     */
    public function forest(): array
    {
        return $this->buildNodes(User::where('role', 'seller')->get(), null);
    }

    /**
     * @return array<int, array{user: User, children: array, descendants_count: int}>
     */
    public function buildNodes(Collection $users, ?int $parentId): array
    {
        return $users->where('parent_id', $parentId)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(function (User $user) use ($users) {
                $children = $this->buildNodes($users, $user->id);

                return [
                    'user' => $user,
                    'children' => $children,
                    'descendants_count' => collect($children)->sum(fn ($c) => 1 + $c['descendants_count']),
                ];
            })->values()->all();
    }

    public function isInSubtree(User $candidate, User $root): bool
    {
        return $this->subtreeUsers($root->id)->contains('id', $candidate->id);
    }

    /** Number of levels in $root's subtree, counting $root itself (leaf = 1). */
    public function subtreeHeight(User $root): int
    {
        return $this->subtreeUsers($root->id)->max('depth') - $root->depth + 1;
    }

    /**
     * Reassign the sponsor and re-cache depth for the whole moved subtree.
     * Validation (cycles, depth budget) is the caller's responsibility.
     */
    public function changeSponsor(User $seller, ?User $newParent): void
    {
        DB::transaction(function () use ($seller, $newParent) {
            $seller->parent_id = $newParent?->id;
            $seller->depth = $newParent ? $newParent->depth + 1 : 1;
            $seller->save();

            $this->recacheChildren($seller);
        });
    }

    protected function recacheChildren(User $parent): void
    {
        foreach ($parent->children()->get() as $child) {
            $child->depth = $parent->depth + 1;
            $child->save();
            $this->recacheChildren($child);
        }
    }

    /**
     * Approved-sales counts in the trailing activity window, as of now.
     * Used for the tree badges.
     *
     * @return array<int, int> seller_id => count
     */
    public function recentSalesCounts(Collection $users): array
    {
        return Sale::whereIn('seller_id', $users->pluck('id'))
            ->where('status', 'approved')
            ->where('sold_at', '>=', now()->subDays((int) config('commissions.activity_window_days')))
            ->selectRaw('seller_id, COUNT(*) as c')
            ->groupBy('seller_id')
            ->pluck('c', 'seller_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }
}
