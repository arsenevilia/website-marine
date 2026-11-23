# Maritor — сайт maritorshipbrokers.com

Статичный сайт (HTML/CSS/JS) + PHP-обработчик формы. Хостинг: Namecheap shared (cPanel).

## Структура
```
index.html        Home (видео + колонка услуг)
about.html        About us
services.html     Services (3 услуги, якоря #brokerage #security #insurance)
contact.html      Contact us (форма)
contact.php       обработчик формы → SMTP → operations@maritorshipbrokers.com
lib/SmtpMailer.php  SMTP-клиент без зависимостей (TLS 465 / STARTTLS 587)
config.sample.php шаблон настроек почты → скопировать в config.php
assets/css, assets/js, assets/img, assets/video
.htaccess         HTTPS, кеш, сжатие, запрет доступа к config/lib
```

## Деплой (cPanel → File Manager или FTP)
1. Загрузить содержимое папки `site/` в `public_html/`.
2. В cPanel → Email Accounts убедиться, что ящик `operations@maritorshipbrokers.com` существует.
3. На сервере скопировать `config.sample.php` → `config.php`, вписать пароль ящика в `smtp_pass`.
4. cPanel → Email Deliverability: включить SPF и DKIM для домена (иначе письма могут уходить в спам).
5. cPanel → SSL/TLS Status: убедиться, что сертификат AutoSSL выпущен для домена.
6. Отправить тестовую заявку с /contact.html.

## Замена медиа (без правки кода)
| Что | Файл(ы) |
|---|---|
| Видео Home | `assets/video/hero.mp4` (десктоп, 1280×720, без обрезки), `hero-mobile.mp4` (мобильные), `hero-poster.jpg` (первый кадр) |
| Фото About | `assets/img/about-port-2400.webp`, `about-port-1200.webp`, `about-port.jpg` |
| Шапка Services | `services-hero-*` |
| Услуги | `service-brokerage-*`, `service-security-*`, `service-insurance-*` |

Видео: H.264, без звука, `-movflags +faststart`, сейчас 40 с (уже зациклено в исходнике), 1280×720, hero.mp4 ≈ 5,9 МБ, hero-mobile.mp4 ≈ 2,3 МБ.

## Временные тексты (помечены `TODO` в коде)
- Home: строка под логотипом в белом блоке.
- About / Services: синяя полоса перед футером («Ready when your vessel is.»).
