# لوحة تحكم إدارة شبكة الانترنت (PHP + MySQL)

واجهة بسيطة لإدارة شبكة الانترنت تعرض مؤشرات الباقات والمشتركين والدعم الفني.

## التشغيل السريع

1. أنشئ قاعدة البيانات واستورد البيانات التجريبية:

```sql
SOURCE schema.sql;
```

2. حدّث بيانات الاتصال بقاعدة البيانات من خلال المتغيرات البيئية:

```bash
export DB_HOST=127.0.0.1
export DB_NAME=internet_dashboard
export DB_USER=root
export DB_PASS=""
```

3. شغّل خادم PHP المحلي:

```bash
php -S 0.0.0.0:8000 -t internet-dashboard
```

ثم افتح المتصفح على `http://localhost:8000`.
