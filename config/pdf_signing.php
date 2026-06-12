<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Удостоверяющая подпись PDF (DocMDP, защита от правки)
    |--------------------------------------------------------------------------
    |
    | Не юридическая КЭП: корпоративный self-signed сертификат для Acrobat/Foxit.
    | При изменении текста подпись становится недействительной (панель подписей / полоска в Reader).
    | DocMDP 2: разрешены только подписи и заполнение форм (контрагент может «влепить» своё).
    |
    | php artisan pdf-signing:generate-certificate
    | PDF_CERTIFY_ENABLED=true
    */

    'enabled' => (bool) env('PDF_CERTIFY_ENABLED', false),

    'certificate_path' => env('PDF_CERTIFY_CERT_PATH', storage_path('app/pdf-signing/cert.pem')),

    'private_key_path' => env('PDF_CERTIFY_KEY_PATH', storage_path('app/pdf-signing/key.pem')),

    'private_key_password' => env('PDF_CERTIFY_KEY_PASSWORD', ''),

    /** 2 = формы и доп. подписи; 3 = ещё аннотации */
    'docmdp' => (int) env('PDF_CERTIFY_DOC_MDP', 2),

    'signer_name' => env('PDF_CERTIFY_SIGNER_NAME', env('APP_NAME', 'CRM')),

    'signer_location' => env('PDF_CERTIFY_SIGNER_LOCATION', 'CRM'),

    'signer_reason' => env('PDF_CERTIFY_SIGNER_REASON', 'Подписание документа в CRM'),

    /** Прямоугольник видимой подписи на последней странице (мм, TCPDF). */
    'appearance' => [
        'x_mm' => (float) env('PDF_CERTIFY_APPEARANCE_X_MM', 130),
        'y_mm' => (float) env('PDF_CERTIFY_APPEARANCE_Y_MM', 255),
        'w_mm' => (float) env('PDF_CERTIFY_APPEARANCE_W_MM', 60),
        'h_mm' => (float) env('PDF_CERTIFY_APPEARANCE_H_MM', 18),
    ],

];
