@props(['value'])

{{-- 重量の表示：必要な桁まで（60.0kg / 62.5kg / 61.25kg。機能設計書5.4） --}}
<span {{ $attributes }}>{{ \App\Support\Format::weight($value) }}</span>
