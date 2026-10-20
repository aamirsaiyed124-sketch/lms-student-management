LMS LOGIN + REGISTRATION FIX (BCA SEM 5)
=======================================

Kya fix hua:
- User Login screen par "Create an account" option add kiya.
- Register page full name, username aur password leta hai.
- Registration backend par password_hash() ke saath password save karta hai.
- Duplicate username reject hota hai.
- Public registration se sirf normal User account banta hai; Admin role public form se nahi ban sakta.
- npm run dev command same rakha gaya hai.

RUN KARNE KA TARIKA (Windows / XAMPP):
1. ZIP extract karke folder ka naam/path rakhein:
   C:\xampp\htdocs\lms-student-management\
   (backend folder is path ke andar hona chahiye.)
2. XAMPP Control Panel mein Apache aur MySQL Start karein.
3. Browser mein http://localhost/phpmyadmin open karein.
4. Import tab se backend/database.sql import karein.
   NOTE: database.sql demo tables ko DROP karke dobara banata hai. Agar aapke paas important existing data hai to pehle backup lein.
5. Agar MySQL username/password alag hai, backend/config/db.php mein DB_HOST, DB_USER, DB_PASS aur DB_NAME update karein.
6. Terminal/CMD kholkar frontend folder mein jaayein:
   cd C:\xampp\htdocs\lms-student-management\frontend
7. Dependencies install karein (pehli baar):
   npm install
8. Frontend start karein:
   npm run dev
9. Vite ka URL open karein, aam taur par http://localhost:5173

DEMO LOGIN:
- Admin: username admin, password Admin@123
- User: username user, password User@123
- Naya account: User Login > Create an account

IMPORTANT:
- npm run dev React frontend chalata hai. PHP API aur MySQL ke liye XAMPP ka Apache/MySQL bhi chalna zaroori hai.
- Registration ke baad User Login par jaakar apne naye username/password se login karein.
- Agar login fail ho to pehle XAMPP Apache/MySQL, database import, aur folder path check karein.
