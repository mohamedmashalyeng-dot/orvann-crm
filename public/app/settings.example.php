<?php
// إعدادات خاصة بالـ CRM — متشاركش الملف ده مع حد ومترفعوش على GitHub.

return [
    // بيانات دخول لوحة التحكم
    'admin_user' => 'admin',
    'admin_password' => 'CHANGE-ME',

    // مفتاح توقيع جلسة الدخول. لو غيرته، كل اللي داخلين هيتسجل خروجهم.
    'session_secret' => 'PUT-64-RANDOM-CHARACTERS-HERE',

    // المفتاح اللي سيرفر الموقع (project.php) بيبعته في X-CRM-Key عشان ميتحسبش عليه حد التسجيلات.
    'integration_key' => 'PUT-48-RANDOM-CHARACTERS-HERE',

    // المواقع المسموح لها تبعت للـ API من المتصفح. ['*'] للكل.
    'allowed_origins' => ['https://orvann.com', 'https://www.orvann.com'],

    // اسم ملف قاعدة البيانات جوه فولدر data (عشوائي عشان محدش يخمنه).
    'db_file' => 'crm-CHANGE-ME.sqlite',

    // مسار كامل لفولدر تاني لقاعدة البيانات لو عايزها بره فولدر الموقع. null = فولدر data اللي هنا.
    'data_dir' => null,

    // true لو عنوان الزائر الحقيقي بييجي في X-Forwarded-For (ورا proxy أو CDN).
    'trust_proxy' => false,
];
