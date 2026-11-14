<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Account\ResumeTrainer;
use App\Actions\Account\SuspendTrainer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * トレーナーの停止・再開。停止状態を1つのリソース（suspension）とみなし、作成＝停止、削除＝再開とする。
 */
class TrainerSuspensionController extends OwnerController
{
    public function store(Request $request, User $trainer, SuspendTrainer $action): RedirectResponse
    {
        $this->authorize('suspend', $trainer);

        $action->handle($request->user(), $trainer);

        return redirect()->route('staff.trainers.index')->with('status', "{$trainer->name}さんを停止しました。");
    }

    public function destroy(Request $request, User $trainer, ResumeTrainer $action): RedirectResponse
    {
        $this->authorize('resume', $trainer);

        $action->handle($request->user(), $trainer);

        return redirect()->route('staff.trainers.index')->with('status', "{$trainer->name}さんの停止を解除しました。");
    }
}
