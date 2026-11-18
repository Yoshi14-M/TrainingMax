<?php

namespace App\View\Components;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

/**
 * 画面上部のナビゲーション。ログイン中のユーザーのロールと、表示対象の利用者（URLの {client}）から項目を決める
 * （機能設計書4.1・4.2）。画面の出し分けは補助であり、認可は必ずサーバー側で行う。
 */
class MainNav extends Component
{
    /** @var array<int, array{label: string, href: string, active: bool}> */
    public array $items = [];

    /** @var array<int, array{label: string, href: string}> */
    public array $menu = [];

    public ?User $target = null;

    public function __construct(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $routeClient = $request->route('client');

        if ($routeClient instanceof User) {
            // 利用者の記録を表示中（利用者本人、または表示対象を選んだスタッフ）
            $this->target = $user->is($routeClient) ? null : $routeClient;
            $this->items[] = $this->item('ホーム', route('records.home', $routeClient), $request->routeIs('records.home'));
            $this->items[] = $this->item('記録', route('records.sessions.today', $routeClient), $request->routeIs('records.sessions.*', 'records.sets.*'));
            $this->items[] = $this->item('体組成', route('records.body.index', $routeClient), $request->routeIs('records.body.*'));
            $this->items[] = $this->item('グラフ', route('records.graphs.training', $routeClient), $request->routeIs('records.graphs.*'));
            $this->items[] = $this->item('種目', route('records.exercises.index', $routeClient), $request->routeIs('records.exercises.*'));

            if ($this->target !== null) {
                // 表示対象を選んでいるスタッフ：利用者一覧へ戻る
                $this->items[] = $this->item('利用者', route('staff.clients.index'), false);
            }
        } elseif ($user->isClient()) {
            $this->items[] = $this->item('ホーム', route('records.home', $user), false);
            $this->items[] = $this->item('記録', route('records.sessions.today', $user), false);
            $this->items[] = $this->item('体組成', route('records.body.index', $user), false);
            $this->items[] = $this->item('グラフ', route('records.graphs.training', $user), false);
            $this->items[] = $this->item('種目', route('records.exercises.index', $user), false);
        } elseif ($user->isActiveStaff()) {
            $this->items[] = $this->item('利用者', route('staff.clients.index'), $request->routeIs('staff.clients.*'));
            $this->items[] = $this->item('種目', route('staff.exercises.index'), $request->routeIs('staff.exercises.*'));
        }

        if ($user->isActiveStaff()) {
            $this->menu[] = ['label' => '利用者アカウント発行', 'href' => route('staff.clients.create')];
            $this->menu[] = ['label' => '招待コード', 'href' => route('staff.invitation-codes.index')];
            if ($user->can('manageTrainers', User::class)) {
                $this->menu[] = ['label' => 'トレーナー管理', 'href' => route('staff.trainers.index')];
            }
            if ($this->target !== null) {
                $this->menu[] = ['label' => 'ジム共通種目の管理', 'href' => route('staff.exercises.index')];
            }
        }

        if ($user->isClient()) {
            $this->menu[] = ['label' => 'ジム連携', 'href' => route('gym-link.show')];
        }
    }

    /**
     * @return array{label: string, href: string, active: bool}
     */
    private function item(string $label, string $href, bool $active): array
    {
        return ['label' => $label, 'href' => $href, 'active' => $active];
    }

    public function render(): View|Closure|string
    {
        return view('components.main-nav');
    }
}
