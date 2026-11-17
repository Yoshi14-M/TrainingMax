{{-- ひとつのグラフに各指標を重ねて表示する。数値は吹き出し（カーソルを合わせる／タップ）で表示する --}}
@if ($hasData)
    <div class="rounded-xl bg-white p-4 shadow" x-data="lineChart(@js($chart))">
        <div class="relative h-80">
            <canvas x-ref="canvas" role="img" aria-label="{{ $label }}のグラフ"></canvas>
        </div>
        <p class="mt-2 text-xs text-gray-500">点にカーソルを合わせる（スマホはタップする）と、その日の各指標の値が表示されます。凡例を押すと、指標ごとに表示・非表示を切り替えられます。</p>
    </div>
@else
    <p class="rounded-xl bg-white p-6 text-sm text-gray-600 shadow">この期間の記録はありません。</p>
@endif
