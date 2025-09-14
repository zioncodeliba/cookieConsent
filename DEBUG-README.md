# WPCCM Debug System

## הפעלת דיבוג

כדי להפעיל את מערכת הדיבוג:

1. פתח את הקובץ `wpccm-debug-config.php`
2. הסר את הסימון `//` מהשורה:
   ```php
   // define('WPCCM_DEBUG', true);
   ```
   כך שתהיה:
   ```php
   define('WPCCM_DEBUG', true);
   ```
3. שמור את הקובץ

## קבצי הלוג

כשהדיבוג מופעל, הלוגים יישמרו ב:
- **wpccm-debug.log** - לוגים מפורטים עם זמנים
- **error.log** של WordPress - לוגים בסיסיים

## מה נרשם בלוגים

### סריקת עוגיות:
- עוגיות שנמצאו בדפדפן
- עיבוד עוגיות (שם, ערך, קטגוריה)
- שמירה לדאטהבייס

### סינכרון אוטומטי:
- הפעלה/ביטול של סינכרון אוטומטי
- ביצוע סינכרון ברקע כל שעה
- בדיקות ידניות של סינכרון אוטומטי

### שמירת נתונים:
- נתונים שמגיעים לפונקציות השמירה
- עדכונים בדאטהבייס
- שגיאות בשמירה

### דוגמאות לוג:
```
[2025-09-14 14:30:15] WPCCM Debug: Processing array cookie: consent_necessary
Array
(
    [value] => 1
)

[2025-09-14 14:30:15] WPCCM Debug: wpccm_save_cookies_to_db called
Array
(
    [0] => Array
        (
            [name] => consent_necessary
            [value] => 1
            [category] => necessary
        )
)
```

## ביטול דיבוג

כדי לבטל את הדיבוג:
1. פתח את `wpccm-debug-config.php`
2. הוסף `//` בתחילת השורה:
   ```php
   // define('WPCCM_DEBUG', true);
   ```
3. שמור את הקובץ

## מחיקת לוגים

כדי למחוק לוגים ישנים:
```bash
rm wpccm-debug.log
```

## פונקציות דיבוג זמינות

### `wpccm_debug_log($message, $data = null)`
רושמת הודעת דיבוג עם זמן ונתונים אופציונליים.

**דוגמאות:**
```php
wpccm_debug_log('Cookie processed successfully');
wpccm_debug_log('Cookie data received', $cookie_array);
```

## מקומות עיקריים עם לוגים

1. **`wpccm_save_cookies_to_db()`** - שמירת עוגיות לדאטהבייס
2. **`ajax_get_current_non_essential_cookies()`** - עיבוד עוגיות מסריקה
3. **`ajax_get_frontend_cookies()`** - קבלת עוגיות מהפרונט
4. **עיבוד כל עוגיה בנפרד** - שם, ערך, קטגוריה

## טיפים לדיבוג

1. **הפעל דיבוג** לפני ביצוע פעולה
2. **בצע את הפעולה** (למשל סריקת עוגיות)
3. **בדוק את הלוג** `wpccm-debug.log`
4. **בטל דיבוג** אחרי סיום
5. **מחק לוגים** ישנים מעת לעת
