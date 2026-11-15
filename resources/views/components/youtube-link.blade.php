@props(['name'])

{{-- 種目名から YouTube の検索リンクを作る（種目名はURLエンコードする）。新しいタブで開くため rel を付ける --}}
<a href="https://www.youtube.com/results?{{ http_build_query(['search_query' => $name.' やり方']) }}"
   target="_blank" rel="noopener noreferrer" {{ $attributes->class('text-blue-700 underline') }}>やり方を見る</a>
