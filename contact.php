<?php
/* =============================================================================
 * ToroDev — ตัวรับฟอร์มติดต่อ
 * -----------------------------------------------------------------------------
 * แก้ค่าใน "ตั้งค่า" ด้านล่างให้เป็นของจริง แล้วอัปโหลดไฟล์นี้ไว้ที่ root
 * ของเว็บ (โฟลเดอร์เดียวกับ index.html) — ไม่ต้องติดตั้งอะไรเพิ่ม
 *
 * ต้องใช้ PHP 7.0 ขึ้นไป (Hostatom / cPanel รองรับอยู่แล้ว)
 * ========================================================================== */

/* ───────────────────────── ตั้งค่า ───────────────────────── */

// อีเมลที่จะรับข้อความจากฟอร์ม (ใส่หลายอันได้ คั่นด้วยจุลภาค)
$MAIL_TO      = 'hello@torodev.co.th';

// อีเมลผู้ส่ง "ต้องเป็นอีเมลที่อยู่บนโดเมนเดียวกับเว็บ" ไม่งั้นเมลจะเข้าสแปม
// สร้างได้จาก cPanel > Email Accounts
$MAIL_FROM    = 'no-reply@torodev.co.th';
$MAIL_FROM_NM = 'ToroDev Website';

// หน้าที่จะเด้งกลับไปหลังส่ง
$REDIRECT     = '/contact.html';

// เก็บสำเนาข้อความไว้เป็นไฟล์ด้วยไหม (กันเมลหาย) — ตั้งเป็น false ถ้าไม่ต้องการ
$KEEP_LOG     = true;
$LOG_FILE     = __DIR__ . '/../torodev-leads.log';   // อยู่นอก public_html เพื่อความปลอดภัย

// กันส่งรัวจาก IP เดิม (วินาที)
$THROTTLE_SEC = 30;

/* ──────────────────────── จบส่วนตั้งค่า ──────────────────────── */


/* ---------- helpers ---------- */

/** ตัดอักขระขึ้นบรรทัดใหม่ออก เพื่อกัน Email Header Injection */
function clean_line($v) {
    return trim(str_replace(array("\r", "\n", "%0a", "%0d", "\0"), ' ', (string)$v));
}

/** ตัดให้สั้นลงและตัดอักขระควบคุมทิ้ง โดยไม่ทำข้อความหายถ้าเจอ encoding แปลก ๆ */
function clean_text($v, $max = 4000) {
    $v = (string)$v;
    $v = str_replace(array("\r\n", "\r"), "\n", $v);

    // เบราว์เซอร์ปกติส่งมาเป็น UTF-8 อยู่แล้ว แต่ถ้าเจอตัวที่ส่งมาเป็น TIS-620
    // (เบราว์เซอร์เก่า หรือข้อความที่ก็อปมาจากโปรแกรมอื่น) ให้แปลงก่อน ไม่ใช่ทิ้ง
    if (function_exists('mb_check_encoding') && !mb_check_encoding($v, 'UTF-8')) {
        if (function_exists('mb_convert_encoding')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8, TIS-620, Windows-874, ISO-8859-11');
        }
    }

    // /u จะคืนค่า null ถ้าข้อความยัง malformed — ต้องมีทางถอยเสมอ
    // ไม่งั้นข้อความของลูกค้าจะหายเงียบ ๆ แล้วเด้งว่า "ข้อมูลไม่ครบ"
    $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    if ($stripped === null) {
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $v);
    }
    if ($stripped !== null) { $v = $stripped; }

    if (function_exists('mb_substr') && mb_check_encoding($v, 'UTF-8')) {
        $v = mb_substr($v, 0, $max, 'UTF-8');
    } else {
        $v = substr($v, 0, $max * 3);
    }
    return trim($v);
}

function bounce($status, $redirect) {
    header('Location: ' . $redirect . '?sent=' . $status . '#formStatus', true, 303);
    exit;
}

function post($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}


/* ---------- 1. รับเฉพาะ POST ---------- */

if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    header('Location: ' . $REDIRECT, true, 303);
    exit;
}


/* ---------- 2. honeypot — บอทมักกรอกช่องที่คนมองไม่เห็น ---------- */

if (clean_line(post('website')) !== '') {
    // แกล้งตอบว่าสำเร็จ เพื่อไม่ให้บอทรู้ว่าโดนจับได้
    bounce('ok', $REDIRECT);
}


/* ---------- 3. กันส่งรัว ---------- */

if ($THROTTLE_SEC > 0) {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    $now  = time();
    $last = isset($_SESSION['td_last_send']) ? (int)$_SESSION['td_last_send'] : 0;
    if ($last && ($now - $last) < $THROTTLE_SEC) {
        bounce('throttled', $REDIRECT);
    }
}


/* ---------- 4. อ่านและตรวจข้อมูล ---------- */

$name     = clean_line(post('name'));
$company  = clean_line(post('company'));
$email    = clean_line(post('email'));
$phone    = clean_line(post('phone'));
$line     = clean_line(post('line'));
$service  = clean_line(post('service'));
$budget   = clean_line(post('budget'));
$timeline = clean_line(post('timeline'));
$message  = clean_text(post('message'), 4000);
$consent  = post('consent') === '1';

$errors = array();

if ($name === '' )                                        { $errors[] = 'name'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'email'; }
if (strlen(preg_replace('/\D/', '', $phone)) < 9)         { $errors[] = 'phone'; }
if ($service === '')                                      { $errors[] = 'service'; }
if ($message === '')                                      { $errors[] = 'message'; }
if (!$consent)                                            { $errors[] = 'consent'; }

if ($errors) {
    bounce('invalid', $REDIRECT);
}

// ชื่อบริการแบบอ่านออก
$SERVICE_LABELS = array(
    'landing'       => 'Landing Page 4 หน้า (เริ่ม ฿5,000)',
    'business'      => 'เว็บไซต์บริษัท (เริ่ม ฿15,000)',
    'commerce'      => 'ร้านค้าออนไลน์ (เริ่ม ฿45,000)',
    'platform'      => 'ระบบ ERP / CRM (เริ่ม ฿120,000)',
    'line-starter'  => 'LINE OA + Rich Menu (เริ่ม ฿3,500)',
    'line-rich'     => 'Rich Menu + Flex Message (เริ่ม ฿9,000)',
    'liff'          => 'LIFF App (เริ่ม ฿18,000)',
    'line-commerce' => 'LINE Commerce & Chatbot (เริ่ม ฿45,000)',
    'automation'    => 'Automation / Excel / VBA (เริ่ม ฿3,500)',
    'scraping'      => 'Web Scraping / Data Pipeline (เริ่ม ฿6,000)',
    'bot'           => 'Automate Script / Bot (เริ่ม ฿5,000)',
    'dashboard'     => 'Dashboard / Power BI (เริ่ม ฿6,500)',
    'pentest'       => 'Penetration Test / VA (เริ่ม ฿15,000)',
    'design'        => 'UX/UI, โลโก้, Branding (เริ่ม ฿4,500)',
    'care'          => 'Website Care (เริ่ม ฿1,500/เดือน)',
    'other'         => 'อื่น ๆ / ยังไม่แน่ใจ',
);
$serviceLabel = isset($SERVICE_LABELS[$service]) ? $SERVICE_LABELS[$service] : $service;


/* ---------- 5. ประกอบข้อความ ---------- */

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? clean_line($_SERVER['HTTP_USER_AGENT']) : '-';
$at = date('d/m/Y H:i:s');

$lines = array(
    'มีคนขอใบเสนอราคาเข้ามาจากเว็บไซต์',
    str_repeat('=', 46),
    '',
    'ชื่อ            : ' . $name,
    'บริษัท          : ' . ($company !== '' ? $company : '-'),
    'อีเมล           : ' . $email,
    'เบอร์โทร        : ' . $phone,
    'LINE ID         : ' . ($line !== '' ? $line : '-'),
    '',
    'บริการที่สนใจ    : ' . $serviceLabel,
    'งบประมาณ        : ' . ($budget !== '' ? $budget : 'ยังไม่ระบุ'),
    'ต้องการงานเมื่อ  : ' . ($timeline !== '' ? $timeline : 'ยังไม่ระบุ'),
    '',
    'รายละเอียดโจทย์',
    str_repeat('-', 46),
    $message,
    '',
    str_repeat('=', 46),
    'ส่งเมื่อ : ' . $at,
    'IP      : ' . $ip,
    'Browser : ' . $ua,
);
$body = implode("\n", $lines);

$subjectRaw = '[ToroDev] ขอใบเสนอราคา — ' . $name . ' (' . $serviceLabel . ')';
$subject    = '=?UTF-8?B?' . base64_encode($subjectRaw) . '?=';
$fromName   = '=?UTF-8?B?' . base64_encode($MAIL_FROM_NM) . '?=';

$headers  = 'From: ' . $fromName . ' <' . $MAIL_FROM . '>' . "\r\n";
$headers .= 'Reply-To: ' . $name . ' <' . $email . '>' . "\r\n";
$headers .= 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
$headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
$headers .= 'X-Mailer: ToroDev-Contact' . "\r\n";


/* ---------- 6. เก็บ log (กันเมลหาย) ---------- */

if ($KEEP_LOG) {
    $entry = "\n\n" . str_repeat('#', 60) . "\n" . $body . "\n";
    @file_put_contents($LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}


/* ---------- 7. ส่งเมล ---------- */

$sent = @mail($MAIL_TO, $subject, $body, $headers, '-f' . $MAIL_FROM);

if ($sent && $THROTTLE_SEC > 0) {
    $_SESSION['td_last_send'] = time();
}

bounce($sent ? 'ok' : 'failed', $REDIRECT);
