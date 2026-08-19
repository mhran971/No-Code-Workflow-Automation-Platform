# دليل تشغيل المشروع عبر Docker (Docker Guide)

تم إعداد بيئة Docker متكاملة لمشروع **No-Code Workflow Automation Platform** لتعمل بكفاءة عالية سواء في بيئة التطوير (Development) أو الإنتاج (Production).

---

## 1. بنية الحاويات (Containers Architecture)

| اسم الحاوية | الخدمة | المنفذ (Port) | الوصف |
|---|---|---|---|
| `workflow_app` | Laravel PHP-FPM 8.2 | 9000 (داخلي) | المعالجة الأساسية للـ API، لوحة تحكم Filament، والمتحكمات |
| `workflow_nginx` | Nginx 1.27 | 8000 (HTTP), 8180 (WS) | خادم الويب العاكس والتوجيه ودعم WebSockets |
| `workflow_postgres` | PostgreSQL 16 | 5432 | قاعدة البيانات الرئيسية |
| `workflow_redis` | Redis 7 | 6379 | التخزين المؤقت، الجلسات، وطوابير العمليات |
| `workflow_queue` | Queue Worker | - | تشغيل مهام تنفيذ تدفقات العمل (`ExecuteNodeJob`) |
| `workflow_scheduler` | Scheduler (Cron) | - | تشغيل المهام الدورية وفحص المؤقتات وتجاوز المهام |
| `workflow_reverb` | Laravel Reverb | 8080 (داخلي) | خادم البث الحي عبر WebSockets |
| `workflow_frontend` | React Vite Canvas | 8082 | تطبيق الواجهة الأمامية وتصميم التدفقات (Canvas) |

---

## 2. البدء السريع (Quick Start)

### الخطوة 1: تجهيز ملف البيئة (.env)
انسخ إعدادات Docker إلى ملف `.env`:
```bash
cp .env.docker.example .env
```

### الخطوة 2: تشغيل الحاويات
باستخدام `docker compose`:
```bash
docker compose up -d --build
```
أو عبر `Makefile` (Linux/macOS):
```bash
make up
```
أو عبر PowerShell (Windows):
```powershell
.\scripts\docker.ps1 up
```

---

## 3. الروابط المتاحة بعد التشغيل

- **لوحة تحكم المشرف (Filament Admin):** [http://localhost:8000/admin](http://localhost:8000/admin)
- **توثيق الـ API (Scramble OpenAPI):** [http://localhost:8000/docs/api](http://localhost:8000/docs/api)
- **الواجهة الأمامية وتصميم التدفقات (Frontend Canvas):** [http://localhost:8082](http://localhost:8082)
- **خادم WebSockets (Laravel Reverb):** `ws://localhost:8180`

---

## 4. الأوامر الشائعة لإدارة البيئة

### تنفيذ الـ Migrations والـ Seeders
```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

### تشغيل الاختبارات (PHPUnit Tests)
```bash
docker compose exec app php artisan test
```

### استعراض سجلات الحاويات (Logs)
```bash
# استعراض كل السجلات
docker compose logs -f

# استعراض سجلات خدمة معينة
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f reverb
```

### الدخول داخل حاوية الـ Backend
```bash
docker compose exec -it app sh
```

### إيقاف الحاويات
```bash
docker compose down
```

---

## 5. مميزات هذا الإعداد

1. **دعم كامل لـ WebSockets:** Nginx و Reverb مهيآن مع ترقية الاتصال (Upgrade Headers) لضمان التحديث المباشر لحالة عقد العمل أثناء التنفيذ.
2. **عزل وتزامن الطوابير (Isolated Queues):** معالجة مهام التدفقات لا تؤثر على سرعة استجابة الـ API.
3. **فحص تلقائي لقاعدة البيانات (Healthcheck & Readiness):** الحاوية تنتظر جاهزية PostgreSQL قبل بدء تشغيل أوامر التهيئة.
4. **تخزين مؤقت واستقرار:** دعم كامل لـ Redis للتخزين والجلسات وبث الأحداث.
