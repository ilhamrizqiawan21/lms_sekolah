# Project map
- Role-oriented single-school LMS. HTTP entrypoint: routes/web.php; auth: app/Http/Controllers/Auth/LoginController.php; role grouping uses auth + role middleware.
- Backend domains: Admin, Guru, Siswa, Kepsek controllers; shared academic models under app/Models; report/export logic in app/Services/Reports and ExportController.
- Authorization is mixed: route role groups plus Policies (KelasMapel, Tugas, WaliKelas) and controller ownership checks. For security work, trace route middleware, policy, and controller relation checks together.
- File downloads are controller-mediated, with some legacy public-disk fallback in student/guru task/material flows.
- References: `mem:tech_stack`, `mem:conventions`, `mem:task_completion`, `mem:suggested_commands`.
