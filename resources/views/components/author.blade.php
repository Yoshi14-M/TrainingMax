@props(['creator', 'updater', 'owner'])

{{-- 入力者・最終更新者の表示：入力：田中トレーナー／更新：本人（停止中のスタッフは「田中（停止中）」。機能設計書5.1） --}}
@php
    $label = fn (?\App\Models\User $user) => $user === null
        ? '不明'
        : ($user->is($owner) ? '本人' : $user->name.($user->isSuspended() ? '（停止中）' : ''));
@endphp
<span {{ $attributes->class('text-xs text-gray-500') }}>入力：{{ $label($creator) }}／更新：{{ $label($updater) }}</span>
