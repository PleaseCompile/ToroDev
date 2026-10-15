# -*- coding: utf-8 -*-
"""
=============================================================================
 ToroDev — รวมไฟล์ที่ต้องอัปโหลดเป็น .zip ไฟล์เดียว
=============================================================================

 รันหลังจากแก้ข้อมูลติดต่อด้วย setup-config.py แล้ว:

     python make-deploy-zip.py

 จะได้ไฟล์ torodev-deploy.zip ที่มีเฉพาะไฟล์ที่ต้องขึ้นโฮสต์
 (ไม่มีไฟล์ต้นฉบับ ไม่มีสคริปต์ ไม่มี README)

 วิธีอัป: cPanel > File Manager > public_html > Upload ไฟล์ zip
          แล้วคลิกขวาที่ไฟล์ > Extract > ลบไฟล์ zip ทิ้ง
=============================================================================
"""

import io
import os
import sys
import zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'torodev-deploy.zip')

ROOT_FILES = [
    'index.html', 'services.html', 'pricing.html', 'about.html',
    'contact.html', '404.html',
    'contact.php', '.htaccess',
    'favicon.svg', 'favicon.ico',
    'robots.txt', 'sitemap.xml', 'site.webmanifest',
]

PLACEHOLDERS = ['torodev.co.th', '@torodev', '080-000-0000']


def main():
    missing = [f for f in ROOT_FILES if not os.path.exists(os.path.join(HERE, f))]
    if missing:
        print('! ไม่พบไฟล์: %s' % ', '.join(missing))
        return 1

    # เตือนถ้ายังไม่ได้เปลี่ยนข้อมูลติดต่อ
    index = io.open(os.path.join(HERE, 'index.html'), encoding='utf-8').read()
    still_default = [p for p in PLACEHOLDERS if p in index]
    if still_default:
        print('')
        print('  ** ยังใช้ข้อมูลตัวอย่างอยู่: %s' % ', '.join(still_default))
        print('  ** ควรรัน  python setup-config.py  ก่อน')
        print('')
        answer = raw_input('  ทำ zip ต่อเลยไหม? (y/n): ') if sys.version_info[0] == 2 \
            else input('  ทำ zip ต่อเลยไหม? (y/n): ')
        if answer.strip().lower() not in ('y', 'yes'):
            print('  ยกเลิก')
            return 1

    if os.path.exists(OUT):
        os.remove(OUT)

    n = 0
    with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name in ROOT_FILES:
            z.write(os.path.join(HERE, name), name)
            n += 1
        for folder, _dirs, files in os.walk(os.path.join(HERE, 'assets')):
            for f in files:
                full = os.path.join(folder, f)
                z.write(full, os.path.relpath(full, HERE).replace('\\', '/'))
                n += 1

    size = os.path.getsize(OUT) / 1024.0
    print('')
    print('  สร้างเสร็จ: torodev-deploy.zip')
    print('  %d ไฟล์  ·  %.0f KB' % (n, size))
    print('')
    print('  ขั้นต่อไป:')
    print('   1. cPanel > File Manager > public_html')
    print('   2. Upload ไฟล์ zip นี้ แล้วคลิกขวา > Extract')
    print('   3. ลบไฟล์ zip บนโฮสต์ทิ้ง')
    print('   4. cPanel > SSL/TLS Status > Run AutoSSL')
    print('   5. cPanel > Email Accounts > สร้าง no-reply@โดเมนของคุณ')
    return 0


if __name__ == '__main__':
    sys.exit(main())
