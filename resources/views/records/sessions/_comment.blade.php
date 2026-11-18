{{-- 1件のコメント（または返信）。書いた人・日時、編集・削除（権限のある人だけ）、本文 --}}
@php
    $isReply = $comment->isReply();
    $author = $comment->author;
    $authorName = $author === null ? '不明' : ($author->is($client) ? '本人' : $author->name.($author->isSuspended() ? '（停止中）' : ''));
    $deleteMessage = $comment->replies->isNotEmpty()
        ? 'このコメントと、返信'.$comment->replies->count().'件を削除します。よろしいですか？'
        : ($isReply ? 'この返信を削除します。よろしいですか？' : 'このコメントを削除します。よろしいですか？');
@endphp
<div class="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-600">
    <span>{{ $isReply ? '↳ ' : '' }}{{ $authorName }}・{{ $comment->created_at->isoFormat('YYYY/MM/DD HH:mm') }}{{ $comment->updated_at->gt($comment->created_at) ? '（編集済み）' : '' }}</span>
    <div class="flex items-center gap-2">
        @can('update', $comment)
            <a class="rounded-md border px-3 py-1 text-sm text-blue-700" href="{{ $baseUrl }}?edit-comment={{ $comment->id }}#comment-{{ $comment->id }}">編集</a>
        @endcan
        @can('delete', $comment)
            <x-confirm-modal :action="route('records.comments.destroy', [$client, $session, $comment])" :message="$deleteMessage" button="削除" />
        @endcan
    </div>
</div>

@if ($editingComment?->id === $comment->id)
    <form method="POST" action="{{ route('records.comments.update', [$client, $session, $comment]) }}">
        @csrf
        @method('PUT')
        <textarea name="body" rows="3" maxlength="1000" required aria-label="コメント" @class(['block w-full rounded-md border px-3 py-2 text-base', $errors->has('body') ? 'border-red-500' : 'border-gray-300'])>{{ old('body', $comment->body) }}</textarea>
        @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <div class="mt-2 flex justify-end gap-2">
            <a class="rounded-md border px-3 py-2 text-sm" href="{{ $baseUrl }}#comment-{{ $comment->id }}">キャンセル</a>
            <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white">更新</button>
        </div>
    </form>
@else
    <p class="whitespace-pre-wrap text-sm">{{ $comment->body }}</p>
@endif
