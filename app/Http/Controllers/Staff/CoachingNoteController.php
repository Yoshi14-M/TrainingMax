<?php

namespace App\Http\Controllers\Staff;

use App\Http\Requests\Staff\StoreCoachingNoteRequest;
use App\Http\Requests\Staff\UpdateCoachingNoteRequest;
use App\Models\CoachingNote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * 指導メモ（スタッフ専用。利用者本人には見せない）。編集・削除は、同じジムのスタッフなら誰でもできる（引き継ぎ用の共有メモ）。
 */
class CoachingNoteController extends StaffController
{
    public function store(StoreCoachingNoteRequest $request, User $client): RedirectResponse
    {
        $note = new CoachingNote($request->validated());
        $note->forceFill([
            'gym_id' => $client->gym_id,
            'client_user_id' => $client->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return $this->back($client, '指導メモを追加しました。', $note);
    }

    public function update(UpdateCoachingNoteRequest $request, User $client, CoachingNote $coachingNote): RedirectResponse
    {
        $coachingNote->fill($request->validated());
        $coachingNote->forceFill(['updated_by' => $request->user()->id])->save();

        return $this->back($client, '指導メモを更新しました。', $coachingNote);
    }

    public function destroy(User $client, CoachingNote $coachingNote): RedirectResponse
    {
        $this->authorize('delete', $coachingNote);

        $coachingNote->delete();

        return $this->back($client, '指導メモを削除しました。');
    }

    private function back(User $client, string $status, ?CoachingNote $note = null): RedirectResponse
    {
        return redirect(route('staff.clients.show', $client).($note === null ? '#notes' : '#note-'.$note->id))->with('status', $status);
    }
}
