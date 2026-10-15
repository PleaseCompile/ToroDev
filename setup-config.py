# -*- coding: utf-8 -*-
"""
=============================================================================
 ToroDev — สคริปต์ตั้งค่าเว็บก่อนอัปโหลดขึ้นโฮสต์
=============================================================================

 ตอนนี้ในไฟล์เว็บยังใช้ข้อมูลติดต่อ "ตัวอย่าง" อยู่ ต้องเปลี่ยนเป็นของจริงก่อน
 วิธีใช้:

   1. แก้ค่าในกล่อง CONFIG ด้านล่างให้เป็นของจริงทั้งหมด
   2. เปิด Command Prompt หรือ PowerShell ตรงโฟลเดอร์นี้ แล้วพิมพ์:

        python setup-config.py

   3. สคริปต์จะไล่แก้ทุกไฟล์ให้ (index.html, services.html, pricing.html,
      about.html, contact.html, 404.html, contact.php, sitemap.xml,
      robots.txt, site.webmanifest)

 สคริปต์นี้รันซ้ำได้ และจะสำรองไฟล์เดิมไว้ในโฟลเดอร์ _backup ให้ทุกครั้ง
 ถ้าอยากแก้ทีหลังอีก ให้เปลี่ยนค่าใน OLD ให้ตรงกับค่าที่ใช้อยู่ตอนนี้
=============================================================================
"""

# ─────────────────────────── CONFIG — แก้ตรงนี้ ───────────────────────────

NEW = {
    # โดเมนจริง ไม่ต้องใส่ https:// และไม่ต้องมี / ปิดท้าย
    'domain':        'torodev.co.th',

    # LINE Official Account — ใส่ทั้ง ID ที่ขึ้นต้นด้วย @ และลิงก์เต็ม
    'line_id':       '@torodev',
    'line_url':      'https://line.me/R/ti/p/@torodev',

    # อีเมลที่จะรับฟอร์มจากลูกค้า
    'email':         'hello@torodev.co.th',

    # อีเมลผู้ส่งของระบบ ต้องเป็นอีเมลบนโดเมนเดียวกัน สร้างใน cPanel > Email Accounts
    'email_sender':  'no-reply@torodev.co.th',

    # เบอร์โทร — 3 รูปแบบ ต้องเป็นเบอร์เดียวกันทั้งหมด
    'phone_display': '080-000-0000',        # แบบที่คนอ่าน
    'phone_link':    '+66800000000',        # แบบที่กดโทรได้ (ตัด 0 ตัวแรก ใส่ +66)
    'phone_schema':  '+66-80-000-0000',     # แบบที่ Google อ่าน

    # เพจ Facebook (ถ้าไม่มี ใส่ค่าเดิมไว้ก็ได้ แล้วค่อยลบลิงก์ออกจากไฟล์ทีหลัง)
    'facebook':      'https://www.facebook.com/torodev',
}

# ค่าที่ใช้อยู่ตอนนี้ในไฟล์ — ปกติไม่ต้องแก้ ถ้ารันครั้งแรก
OLD = {
    'domain':        'torodev.co.th',
    'line_id':       '@torodev',
    'line_url':      'https://line.me/R/ti/p/@torodev',
    'email':         'hello@torodev.co.th',
    'email_sender':  'no-reply@torodev.co.th',
    'phone_display': '080-000-0000',
    'phone_link':    '+66800000000',
    'phone_schema':  '+66-80-000-0000',
    'facebook':      'https://www.facebook.com/torodev',
}

# ──────────────────────── จบส่วนที่ต้องแก้ ────────────────────────

import io
import os
import shutil
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
BACKUP = os.path.join(HERE, '_backup')

TARGETS = [
    'index.html', 'services.html', 'pricing.html', 'about.html',
    'contact.html', '404.html', 'contact.php',
    'sitemap.xml', 'robots.txt', 'site.webmanifest',
]

# ลำดับสำคัญ: ต้องแทนตัวที่ยาวกว่าก่อน ไม่งั้น domain จะไปกินอีเมลกับ URL
ORDER = [
    'line_url', 'email_sender', 'email', 'facebook',
    'phone_link', 'phone_schema', 'phone_display',
    'domain', 'line_id',
]


def main():
    missing = [f for f in TARGETS if not os.path.exists(os.path.join(HERE, f))]
    if missing:
        print('! ไม่พบไฟล์: %s' % ', '.join(missing))
        print('  รันสคริปต์นี้ในโฟลเดอร์เดียวกับ index.html')
        return 1

    changes = [(k, OLD[k], NEW[k]) for k in ORDER if OLD[k] != NEW[k]]
    if not changes:
        print('ยังไม่ได้แก้ค่าใน CONFIG เลย — เปิดไฟล์นี้แล้วแก้ค่าในกล่อง NEW ก่อน')
        return 1

    print('จะเปลี่ยนค่าเหล่านี้:')
    for key, old, new in changes:
        print('  %-14s %s  ->  %s' % (key, old, new))
    print('')

    if not os.path.isdir(BACKUP):
        os.makedirs(BACKUP)

    total = 0
    for name in TARGETS:
        path = os.path.join(HERE, name)
        text = io.open(path, encoding='utf-8').read()
        original = text

        # แทนที่แบบสองจังหวะ: ของเก่า -> โทเคนชั่วคราว -> ของใหม่
        # ถ้าแทนรวดเดียวเรียงกัน ค่าที่แทนไปแล้วจะโดนกฎถัดไปแทนซ้ำ
        # เช่น @torodev -> @torodevth แล้วโดนกฎ @torodev ซ้ำจนกลายเป็น @torodevthth
        hits = 0
        for i, (_key, old, _new) in enumerate(changes):
            hits += text.count(old)
            text = text.replace(old, '\x00TD%d\x00' % i)
        for i, (_key, _old, new) in enumerate(changes):
            text = text.replace('\x00TD%d\x00' % i, new)

        if text != original:
            shutil.copy2(path, os.path.join(BACKUP, name))
            io.open(path, 'w', encoding='utf-8', newline='\n').write(text)
            total += hits
            print('  แก้แล้ว %-20s %d จุด' % (name, hits))
        else:
            print('  ไม่มีอะไรต้องแก้ %s' % name)

    print('')
    print('เสร็จแล้ว — แก้ไปทั้งหมด %d จุด' % total)
    print('ไฟล์เดิมสำรองไว้ที่: %s' % BACKUP)
    print('')
    print('อย่าลืม:')
    print('  1. เปิด contact.php เช็กว่า $MAIL_TO และ $MAIL_FROM ถูกต้อง')
    print('  2. ก็อปค่าใน NEW ไปทับใน OLD เผื่อต้องรันสคริปต์นี้อีกรอบ')
    print('  3. ลบโฟลเดอร์ _backup ก่อนอัปโหลดขึ้นโฮสต์')
    return 0


if __name__ == '__main__':
    sys.exit(main())
