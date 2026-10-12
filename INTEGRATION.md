# ربط فورم orvann.com بالـ CRM

الفورم المقصود: **Start a Project** في صفحة Contact (`src/components/page/ProjectForm.tsx` في مشروع `orvann-web`)، واللي بيبعت لـ `public/api/project.php`.

## الفكرة

```
العميل يملأ الفورم  →  project.php  →  ١) إيميل على info@orvann.com (زي ما هو)
                                      ٢) نسخة للـ CRM على POST /api/leads
```

الربط بيحصل من سيرفر لسيرفر جوه `project.php`، يعني مفيش CORS ومفيش مفتاح بيوصل للمتصفح. ولو الـ CRM واقع، الإيميل بيتبعت عادي والعميل مش بيحس بحاجة.

> **الـ CRM اتحول من Node لـ PHP** عشان يشتغل على نفس استضافة Hostinger. العقد مع الموقع متغيرش: نفس الرابط `/api/leads`، نفس الحقول والـ ids، نفس المفتاح، ونفس الرد `{"ok":true,"id":…}`. اللي اتغير أسماء الملفات بس:
>
> | كان | بقى |
> |---|---|
> | `INTEGRATION_KEY` في `.env` | `integration_key` في `public/app/settings.php` (نفس القيمة) |
> | القوائم في `config.js` | القوائم في `public/app/config.php` (نفس الـ ids) |

---

## الخطوة ١ — رفع الـ CRM

ارفع `crm-upload.zip` على subdomain زي `crm.orvann.com` على نفس الاستضافة. الخطوات وجدول الفحص بعد الرفع في [README.md](README.md).

## الخطوة ٢ — الموقع يعرف عنوان الـ CRM والمفتاح

`project.php` في `orvann-web` بيقرأ الإعدادات دي من ملف `public/api/crm-config.php` (مش بيترفع على GitHub). انسخه من `crm-config.example.php` واملأه:

```php
return [
    'url' => 'https://crm.orvann.com/api/leads',
    'key' => 'قيمة integration_key من public/app/settings.php بتاع الـ CRM',
];
```

وبعدين build وارفع الموقع زي العادة.

- لو `https://crm.orvann.com/api/options` بيرجع 404 بعد الرفع، يبقى إعادة كتابة الروابط مش شغالة على فولدر الـ CRM. استخدم الرابط ده بدله وهيشتغل: `https://crm.orvann.com/api/index.php?r=leads`
- المفتاح بيخلي سيرفر الموقع ميتحسبش عليه حد التسجيلات (10 كل 10 دقايق لكل عنوان). ملوش أي صلاحية قراءة.

## الخطوة ٣ — التجربة

1. جرّب الـ CRM لوحده (حط المفتاح):

   ```bash
   curl -X POST https://crm.orvann.com/api/leads \
     -H "Content-Type: application/json" -H "X-CRM-Key: المفتاح" \
     -d '{"full_name":"Test Lead","email":"test@example.com","service":"branding","job_title":"owner","region":"cairo","timeline":"asap"}'
   ```

   المفروض يرجع `{"ok":true,"id":...}` والعميل يظهر في اللوحة بأولوية عالية.
2. املأ فورم الموقع بنفسك.
3. اتأكد من ٣ حاجات: الإيميل وصل وفيه سطر `CRM` بيقول إنه اتسجل، والعميل ظهر في `/admin/`، والمصدر مكتوب `orvann.com (en)` أو `(ar)`.
4. سجّل تاني بنفس الإيميل. المفروض **ميتعملش** عميل جديد، ويتكتب في ملاحظات العميل القديم إنه سجّل تاني.
5. امسح عملاء التجربة من اللوحة.

---

## مرجع الـ API

`POST /api/leads` — JSON (أو فورم HTML عادي):

| الحقل | ملاحظات |
|---|---|
| `phone` / `email` | **واحد منهم على الأقل لازم يكون صحيح**، وده الشرط الوحيد. الرقم بأي صيغة والـ CRM بيوحّده |
| `full_name` | أي طول. لو فاضي، العميل بيظهر بإيميله أو رقمه |
| `company` `city` `message` | نص حر |
| `job_title` `region` `service` `timeline` `budget` | الـ id من القوائم تحت، أو الاسم زي ما هو في `config.php` |
| `source` | بيظهر في اللوحة كمصدر العميل |
| `website` | مصيدة سبام: لو اتملت، الطلب بيتجاهل ويرد `ok` |

الهيدر `X-CRM-Key` للسيرفرات بس، وبيعدّي حد التسجيلات.

بيرد بـ `201` لعميل جديد، و `200` لعميل موجود (نفس الرقم أو الإيميل)، و `400` مع `errors` لو مفيش ولا وسيلة تواصل صحيحة، و `429` لو اتعدى حد التسجيلات من غير المفتاح.

الـ CRM مبيرفضش تسجيل الموقع قبله: اسم قصير أو ناقص، أو إيميل غلط جنب رقم صحيح، كله بيتسجل، والحاجة اللي مش مفهومة بتتكتب في آخر رسالة العميل.

القيمة اللي الـ CRM ميعرفهاش في أي قايمة بتتكتب في آخر رسالة العميل، والعميل بيتحفظ عادي، فمفيش عميل ولا معلومة بتضيع لو القوائم اختلفت بين الموقع والـ CRM.

### القوائم (الـ ids)

القايمة الكاملة بالأسماء متاحة دايماً من `GET /api/options`، ومصدرها `public/app/config.php`. لو اتغيرت هناك، تتغير في الموقع كمان (`src/content/lead-options.ts` و `project.php`).

**`job_title`:** `owner` صاحب شركة / مؤسس · `ceo` رئيس تنفيذي / مدير عام · `marketing-head` مدير تسويق · `manager` مدير قسم / إدارة · `employee` موظف / أخصائي · `freelancer` عمل حر · `student` طالب · `other` أخرى

**`service`:** `strategy` · `branding` · `marketing` · `digital` · `events` · `multiple` · `unsure`

**`timeline`:** `asap` فوراً · `month` خلال شهر · `quarter` خلال 3 شهور · `exploring` مجرد استفسار

**`budget`:** `lt-10k` أقل من 10,000 جنيه · `10k-50k` · `50k-200k` · `gt-200k` أكثر من 200,000 جنيه · `unknown` غير محدد

**`region`** (المنطقة بتتحدد لوحدها في الـ CRM):

| المنطقة | القيم |
|---|---|
| القاهرة الكبرى | `cairo` `giza` `qalyubia` |
| الإسكندرية والساحل | `alexandria` `beheira` `matrouh` |
| الدلتا | `sharqia` `dakahlia` `gharbia` `monufia` `kafr-el-sheikh` `damietta` |
| القناة وسيناء | `port-said` `ismailia` `suez` `north-sinai` `south-sinai` |
| شمال الصعيد | `fayoum` `beni-suef` `minya` |
| جنوب الصعيد | `assiut` `sohag` `qena` `luxor` `aswan` |
| البحر الأحمر والوادي الجديد | `red-sea` `new-valley` |
| الخليج | `saudi` `uae` `kuwait` `qatar` `bahrain` `oman` |
| دول أخرى | `other-country` |
