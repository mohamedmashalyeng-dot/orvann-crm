<?php
// عدّل القوائم دي براحتك — الفورم واللوحة بيقروا منها تلقائياً.
// id: القيمة اللي الموقع بيبعتها للـ API (متغيرهاش بعد الربط). name: اللي بيظهر في اللوحة.
// weight: بيتجمع عشان يحسب "نقاط" العميل، والنقاط بتحدد الأولوية تلقائياً.

return [
    // "النهارده" وفلتر التاريخ في اللوحة بيتحسبوا بالتوقيت ده.
    'timezone' => 'Africa/Cairo',

    'regions' => [
        ['id' => 'cairo', 'name' => 'القاهرة', 'zone' => 'القاهرة الكبرى'],
        ['id' => 'giza', 'name' => 'الجيزة', 'zone' => 'القاهرة الكبرى'],
        ['id' => 'qalyubia', 'name' => 'القليوبية', 'zone' => 'القاهرة الكبرى'],
        ['id' => 'alexandria', 'name' => 'الإسكندرية', 'zone' => 'الإسكندرية والساحل'],
        ['id' => 'beheira', 'name' => 'البحيرة', 'zone' => 'الإسكندرية والساحل'],
        ['id' => 'matrouh', 'name' => 'مطروح', 'zone' => 'الإسكندرية والساحل'],
        ['id' => 'sharqia', 'name' => 'الشرقية', 'zone' => 'الدلتا'],
        ['id' => 'dakahlia', 'name' => 'الدقهلية', 'zone' => 'الدلتا'],
        ['id' => 'gharbia', 'name' => 'الغربية', 'zone' => 'الدلتا'],
        ['id' => 'monufia', 'name' => 'المنوفية', 'zone' => 'الدلتا'],
        ['id' => 'kafr-el-sheikh', 'name' => 'كفر الشيخ', 'zone' => 'الدلتا'],
        ['id' => 'damietta', 'name' => 'دمياط', 'zone' => 'الدلتا'],
        ['id' => 'port-said', 'name' => 'بورسعيد', 'zone' => 'القناة وسيناء'],
        ['id' => 'ismailia', 'name' => 'الإسماعيلية', 'zone' => 'القناة وسيناء'],
        ['id' => 'suez', 'name' => 'السويس', 'zone' => 'القناة وسيناء'],
        ['id' => 'north-sinai', 'name' => 'شمال سيناء', 'zone' => 'القناة وسيناء'],
        ['id' => 'south-sinai', 'name' => 'جنوب سيناء', 'zone' => 'القناة وسيناء'],
        ['id' => 'fayoum', 'name' => 'الفيوم', 'zone' => 'شمال الصعيد'],
        ['id' => 'beni-suef', 'name' => 'بني سويف', 'zone' => 'شمال الصعيد'],
        ['id' => 'minya', 'name' => 'المنيا', 'zone' => 'شمال الصعيد'],
        ['id' => 'assiut', 'name' => 'أسيوط', 'zone' => 'جنوب الصعيد'],
        ['id' => 'sohag', 'name' => 'سوهاج', 'zone' => 'جنوب الصعيد'],
        ['id' => 'qena', 'name' => 'قنا', 'zone' => 'جنوب الصعيد'],
        ['id' => 'luxor', 'name' => 'الأقصر', 'zone' => 'جنوب الصعيد'],
        ['id' => 'aswan', 'name' => 'أسوان', 'zone' => 'جنوب الصعيد'],
        ['id' => 'red-sea', 'name' => 'البحر الأحمر', 'zone' => 'البحر الأحمر والوادي الجديد'],
        ['id' => 'new-valley', 'name' => 'الوادي الجديد', 'zone' => 'البحر الأحمر والوادي الجديد'],
        ['id' => 'saudi', 'name' => 'السعودية', 'zone' => 'الخليج'],
        ['id' => 'uae', 'name' => 'الإمارات', 'zone' => 'الخليج'],
        ['id' => 'kuwait', 'name' => 'الكويت', 'zone' => 'الخليج'],
        ['id' => 'qatar', 'name' => 'قطر', 'zone' => 'الخليج'],
        ['id' => 'bahrain', 'name' => 'البحرين', 'zone' => 'الخليج'],
        ['id' => 'oman', 'name' => 'عُمان', 'zone' => 'الخليج'],
        ['id' => 'other-country', 'name' => 'دولة أخرى', 'zone' => 'دول أخرى'],
    ],

    'job_titles' => [
        ['id' => 'owner', 'name' => 'صاحب شركة / مؤسس', 'weight' => 3],
        ['id' => 'ceo', 'name' => 'رئيس تنفيذي / مدير عام', 'weight' => 3],
        ['id' => 'marketing-head', 'name' => 'مدير تسويق', 'weight' => 3],
        ['id' => 'manager', 'name' => 'مدير قسم / إدارة', 'weight' => 2],
        ['id' => 'employee', 'name' => 'موظف / أخصائي', 'weight' => 1],
        ['id' => 'freelancer', 'name' => 'عمل حر', 'weight' => 1],
        ['id' => 'student', 'name' => 'طالب', 'weight' => 0],
        ['id' => 'other', 'name' => 'أخرى', 'weight' => 0],
    ],

    // نفس خدمات فورم "Start a Project" على orvann.com (الـ id = قيمة حقل need هناك).
    'services' => [
        ['id' => 'strategy', 'name' => 'Strategy & Consulting', 'weight' => 2],
        ['id' => 'branding', 'name' => 'Branding & Creative', 'weight' => 2],
        ['id' => 'marketing', 'name' => 'Marketing & Media', 'weight' => 2],
        ['id' => 'digital', 'name' => 'Digital Experiences', 'weight' => 2],
        ['id' => 'events', 'name' => 'Events, Outdoor & Production', 'weight' => 2],
        ['id' => 'multiple', 'name' => 'Multiple Services', 'weight' => 3],
        ['id' => 'unsure', 'name' => 'Not Sure Yet', 'weight' => 0],
    ],

    'budgets' => [
        ['id' => 'lt-10k', 'name' => 'أقل من 10,000 جنيه', 'weight' => 0],
        ['id' => '10k-50k', 'name' => 'من 10,000 إلى 50,000 جنيه', 'weight' => 1],
        ['id' => '50k-200k', 'name' => 'من 50,000 إلى 200,000 جنيه', 'weight' => 2],
        ['id' => 'gt-200k', 'name' => 'أكثر من 200,000 جنيه', 'weight' => 3],
        ['id' => 'unknown', 'name' => 'غير محدد', 'weight' => 0],
    ],

    'timelines' => [
        ['id' => 'asap', 'name' => 'فوراً', 'weight' => 3],
        ['id' => 'month', 'name' => 'خلال شهر', 'weight' => 2],
        ['id' => 'quarter', 'name' => 'خلال 3 شهور', 'weight' => 1],
        ['id' => 'exploring', 'name' => 'مجرد استفسار', 'weight' => 0],
    ],

    // النقاط >= high → أولوية عالية، >= medium → متوسطة، غير كده منخفضة
    'priority_thresholds' => ['high' => 7, 'medium' => 4],

    'priorities' => ['high' => 'عالية', 'medium' => 'متوسطة', 'low' => 'منخفضة'],

    'statuses' => ['new' => 'جديد', 'contacted' => 'تم التواصل', 'interested' => 'مهتم', 'negotiation' => 'تفاوض', 'won' => 'تم البيع', 'lost' => 'غير مهتم'],
];
