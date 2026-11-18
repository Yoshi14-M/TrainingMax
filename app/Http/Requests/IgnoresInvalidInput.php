<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * 画面を開くためのURLパラメータ（検索語・並び順・期間・年月など）の検証。
 * 不正な値は、エラー画面にせず初期値に置き換える（URLを手で書き換えただけの場合に操作を止めないため。セキュリティ設計書6.9）。
 * 使うクラスは、検証に通った項目だけを validInput() で取り出す。
 */
trait IgnoresInvalidInput
{
    /**
     * 入力エラーでもリダイレクトせず、画面を表示する。
     */
    protected function failedValidation(Validator $validator): void
    {
        // 何もしない（validInput() が、不正な項目を無視する）
    }

    /**
     * 検証に通った項目の値を返す（エラーのある項目は null）。
     */
    protected function validInput(string $key): mixed
    {
        return $this->validator->errors()->has($key) ? null : $this->input($key);
    }
}
