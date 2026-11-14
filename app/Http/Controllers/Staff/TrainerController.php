<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Account\AddTrainer;
use App\Http\Requests\Staff\AddTrainerRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * トレーナー管理（S-24。オーナーのみ）。
 */
class TrainerController extends OwnerController
{
    public function index(Request $request): View
    {
        $trainers = User::query()
            ->trainers()
            ->where('gym_id', $request->user()->gym_id)
            ->orderBy('id')
            ->get();

        return view('staff.trainers.index', ['trainers' => $trainers]);
    }

    public function store(AddTrainerRequest $request, AddTrainer $action): RedirectResponse
    {
        $trainer = $action->handle($request->user(), $request->validated());

        return redirect()->route('staff.trainers.index')
            ->with('status', "{$trainer->name}さんを追加しました。パスワード設定メールを送信しました。");
    }
}
