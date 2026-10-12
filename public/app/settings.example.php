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

    // مكان قاعدة البيانات. null = تلقائي: فولدر crm-data بره فولدر الموقع لو الاستضافة تسمح
    // (فتفضية فولدر الـ CRM متمسحش العملاء)، وإلا فولدر data اللي هنا. أو اكتب مسار كامل.
    'data_dir' => null,

    // true لو عنوان الزائر الحقيقي بييجي في X-Forwarded-For (ورا proxy أو CDN).
    'trust_proxy' => false,
];
