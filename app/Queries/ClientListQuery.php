<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * 利用者一覧（S-20）の取得。同じジムに現在所属している利用者だけを、最終トレーニング日つきで返す（機能設計書F-05）。
 */
class ClientListQuery
{
    public const LAST_TRAINED = 'last_trained';

    public const NAME = 'name';

    public const DEFAULT_SORT = self::LAST_TRAINED;

    /**
     * 並び順の許可リスト（カラム名に関わるパラメータは許可リストで変換する。セキュリティ設計書7.3）。
     *
     * @var array<int, string>
     */
    public const SORTS = [self::LAST_TRAINED, self::NAME];

    public const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(User $staff, ?string $keyword, string $sort): LengthAwarePaginator
    {
        $query = User::query()
            ->clients()
            ->where('gym_id', $staff->gym_id)
            ->withMax('workoutSessions as last_trained_on', 'performed_on');

        if ($keyword !== null) {
            $query->where('name', 'like', '%'.$this->escapeLike($keyword).'%');
        }

        $this->order($query, $sort);

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * 並び順。最終トレーニング日は新しい順で、記録がない利用者は末尾。同じ順位は名前順。
     *
     * @param  Builder<User>  $query
     */
    private function order(Builder $query, string $sort): void
    {
        if ($sort === self::NAME) {
            $query->orderBy('name')->orderBy('id');

            return;
        }

        $query->orderByDesc('last_trained_on')->orderBy('name')->orderBy('id');
    }

    /**
     * LIKE の特殊文字（%・_・\）を、文字として検索するためにエスケープする。
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
