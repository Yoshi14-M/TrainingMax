<?php

namespace Tests\Support;

/**
 * 画面に埋め込まれた Alpine.js コンポーネントの設定（`x-data="名前(JSON.parse('...'))"`）を取り出す。
 */
trait ReadsAlpineConfig
{
    /**
     * @return array<string, mixed>
     */
    protected function alpineConfig(string $html, string $component): array
    {
        preg_match('/x-data="'.preg_quote($component, '/').'\(JSON\.parse\(\'(.*?)\'\)\)"/s', $html, $match);

        // JavaScript の文字列リテラルとして埋め込まれているため、\uXXXX と重ねたバックスラッシュのエスケープを戻してから JSON として読む
        $json = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', fn ($m) => mb_chr(hexdec($m[1])), $match[1] ?? '');
        $json = str_replace(['\\\\\\/', '\\\\'], ['/', '\\'], $json);

        return json_decode($json, true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    protected function dataset(array $chart, string $key): array
    {
        foreach ($chart['datasets'] as $dataset) {
            if ($dataset['key'] === $key) {
                return $dataset;
            }
        }

        $this->fail("指標 {$key} がありません。");
    }
}
