
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Barangay Guindacpan | Official Website</title>
    <link rel="icon" type="image/png" href="asset/logo.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        green900: '#0B3D2E',
                        green800: '#0E4A37',
                        green700: '#14532D',
                        gold: '#B9972F',
                        gold2: '#C9A227',
                        cream: '#EAF2E9'
                    }
                }
            }
        };
    </script>

    <!-- Custom CSS -->
    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .fade-slide {
            opacity: 1;
            transition: opacity 1s ease-in-out;
        }

        .fade-slide.fade-out {
            opacity: 0;
        }

        .service-card,
        .official-card,
        .announcement-card {
            transition: all 0.3s ease;
        }

        .service-card:hover,
        .official-card:hover,
        .announcement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.09);
        }

        .hero-overlay {
            background: linear-gradient(
                90deg,
                rgba(11, 61, 46, 0.96) 0%,
                rgba(11, 61, 46, 0.80) 45%,
                rgba(11, 61, 46, 0.15) 100%
            );
        }

        .section-label {
            letter-spacing: 2px;
        }

        #mobileMenu {
            transition: max-height 0.35s ease, opacity 0.3s ease;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
        }

        #mobileMenu.open {
            max-height: 400px;
            opacity: 1;
        }
    </style>

</head>

<body class="bg-gray-50 text-gray-800">

    <!-- ==========================================
         NAVIGATION BAR
    =========================================== -->

    <nav class="bg-white shadow-sm sticky top-0 z-50">

        <div class="max-w-7xl mx-auto px-5 md:px-10">

            <div class="flex justify-between items-center py-3">

                <!-- LOGO AND BRAND -->

                <a href="main.php" class="flex items-center gap-3">

                    <img
                        src="asset/logo.png"
                        alt="Barangay Guindacpan Logo"
                        class="w-12 h-12 md:w-14 md:h-14 rounded-full border-2 border-gold object-cover"
                    >

                    <div>
                        <h1 class="text-lg md:text-xl font-extrabold text-green900 tracking-tight">
                            BARANGAY GUINDACPAN
                        </h1>

                        <p class="text-xs md:text-sm text-gray-500">
                            Talibon, Bohol • Philippines
                        </p>
                    </div>

                </a>

                <!-- DESKTOP NAVIGATION -->

                <div class="hidden md:flex items-center gap-7">

                    <a href="main.php"
                       class="text-green900 font-bold border-b-2 border-gold pb-1">
                        Home
                    </a>

                    <a href="services.php"
                       class="text-gray-600 hover:text-green900 transition font-medium">
                        Services
                    </a>

                    <a href=""
                       class="text-gray-600 hover:text-green900 transition font-medium">
                        Officials
                    </a>

                    <a href="announcements.php"
                       class="text-gray-600 hover:text-green900 transition font-medium">
                        Announcements
                    </a>

                    <!-- RESIDENT LOGIN -->

                    <a href="#residentLogin" data-login-open
                       class="bg-green900 text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-green800 transition shadow-sm">
                        👤CREATE ACCOUNT
                    </a>

                </div>

                <!-- MOBILE MENU BUTTON -->

                <button
                    id="menuBtn"
                    type="button"
                    class="md:hidden text-green900 text-3xl focus:outline-none"
                    aria-label="Toggle navigation"
                    aria-expanded="false"
                    aria-controls="mobileMenu"
                >
                    ☰
                </button>

            </div>

            <!-- MOBILE NAVIGATION -->

            <div id="mobileMenu" class="md:hidden">

                <div class="flex flex-col gap-1 pb-4">

                    <a href="main.php"
                       class="px-4 py-3 rounded-lg bg-cream text-green900 font-bold">
                        Home
                    </a>

                    <a href="services.php"
                       class="px-4 py-3 rounded-lg hover:bg-cream transition">
                        Services
                    </a>

                    <a href="officials.php"
                       class="px-4 py-3 rounded-lg hover:bg-cream transition">
                        Officials
                    </a>

                    <a href="announcements.php"
                       class="px-4 py-3 rounded-lg hover:bg-cream transition">
                        Announcements
                    </a>

                    <!-- RESIDENT LOGIN -->

                    <a href="#residentLogin" data-login-open
                       id="logIn" class="px-4 py-3 bg-green900 text-white rounded-lg text-center font-semibold mt-2">
                        👤 CREATE ACCOUNT
                    </a>

                </div>
            </div>

        </div>

    </nav>

    <!-- ==========================================
         HERO SECTION WITH IMAGE SLIDESHOW
    =========================================== -->

    <section class="relative overflow-hidden">

        <!-- BACKGROUND IMAGE -->

        <img
            id="slide"
            src="asset/bg.jpg"
            alt="Barangay Guindacpan Community"
            class="absolute inset-0 w-full h-full object-cover fade-slide"
        >

        <!-- OVERLAY -->

        <div class="absolute inset-0 hero-overlay"></div>

        <!-- HERO CONTENT -->

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-10">

            <div class="min-h-[540px] md:min-h-[600px] flex items-center">

                <div class="max-w-3xl py-16 md:py-20">

                    <!-- LABEL -->

                    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 text-gold2 px-4 py-2 rounded-full text-xs font-bold uppercase section-label mb-6">

                        <span class="w-2 h-2 bg-gold2 rounded-full"></span>

                        Official Barangay Website

                    </div>

                    <!-- HEADING -->

                    <h2 class="text-white text-4xl md:text-6xl font-extrabold leading-tight tracking-tight">

                        Maligayang Pagdating sa

                        <span class="text-gold2">
                            Barangay Guindacpan
                        </span>

                    </h2>

                    <!-- DESCRIPTION -->

                    <p class="text-white/85 text-base md:text-lg leading-relaxed mt-6 max-w-2xl">

                        Welcome to the official online portal of Barangay Guindacpan, Talibon, Bohol.

                        Access community services, important announcements, and public information in one convenient place.

                    </p>

                    <!-- HERO BUTTONS -->

                    <div class="flex flex-col sm:flex-row gap-3 mt-8">
                    <a
    href="resident/request.php"
    class="bg-gold hover:bg-gold2 text-white px-7 py-3.5 rounded-lg font-bold text-sm text-center shadow-lg transition"
>
    📋 Request a Document
</a>

                        <a
                            href="track/tracking.php"
                            class="bg-white/10 hover:bg-white/20 border border-white/40 text-white px-7 py-3.5 rounded-lg font-bold text-sm text-center transition backdrop-blur-sm"
                        >
                            🔍 Track Your Request
                        </a>

                    </div>

                    <!-- SMALL INFORMATION -->

                    <div class="flex flex-wrap gap-6 mt-10 text-white/80 text-sm">

                        <div class="flex items-center gap-2">
                            <span class="text-gold2">✓</span>
                            Accessible Services
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-gold2">✓</span>
                            Community Information
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-gold2">✓</span>
                            Public Transparency
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ==========================================
         BARANGAY STATISTICS
    =========================================== -->

    <section class="relative z-20 -mt-8 max-w-7xl mx-auto px-5 md:px-10">

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">

            <!-- RESIDENTS -->

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 flex items-center gap-5">

                <div class="w-14 h-14 rounded-xl bg-green900 text-white flex items-center justify-center text-2xl shrink-0">
                    👥
                </div>

                <div>
                    <p class="text-sm text-gray-500 font-medium">
                        Total Residents
                    </p>

                    <h3 class="text-3xl font-extrabold text-green900 mt-1">
                        4,250
                    </h3>

                    <p class="text-xs text-gray-400 mt-1">
                        Community Population
                    </p>
                </div>

            </div>

            <!-- HOUSEHOLDS -->

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 flex items-center gap-5">

                <div class="w-14 h-14 rounded-xl bg-gold text-white flex items-center justify-center text-2xl shrink-0">
                    🏠
                </div>

                <div>
                    <p class="text-sm text-gray-500 font-medium">
                        Total Households
                    </p>

                    <h3 class="text-3xl font-extrabold text-green900 mt-1">
                        1,120
                    </h3>

                    <p class="text-xs text-gray-400 mt-1">
                        Registered Households
                    </p>
                </div>

            </div>

            <!-- DOCUMENTS -->

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 flex items-center gap-5">

                <div class="w-14 h-14 rounded-xl bg-green700 text-white flex items-center justify-center text-2xl shrink-0">
                    📄
                </div>

                <div>
                    <p class="text-sm text-gray-500 font-medium">
                        Clearance Issued Today
                    </p>

                    <h3 class="text-3xl font-extrabold text-green900 mt-1">
                        15
                    </h3>

                    <p class="text-xs text-gray-400 mt-1">
                        Barangay Document Services
                    </p>
                </div>

            </div>

        </div>

    </section>


    <!-- ==========================================
         SERVICES SECTION
    =========================================== -->

    <section class="max-w-7xl mx-auto px-5 md:px-10 py-20">

        <!-- SECTION HEADING -->

        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">

            <div>

                <p class="text-gold font-bold text-xs uppercase section-label">
                    Public Services
                </p>

                <h2 class="text-3xl md:text-4xl font-extrabold text-green900 mt-2">
                    Our Barangay Services
                </h2>

                <p class="text-gray-500 mt-3 max-w-2xl leading-relaxed">
                    Access essential barangay documents and services. Our goal is to make public services more convenient and accessible to every resident.
                </p>

            </div>

            <a
                href="services.php"
                class="text-green900 font-bold text-sm hover:text-gold transition"
            >
                View All Services →
            </a>

        </div>

        <!-- SERVICE CARDS -->

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5 mt-10">

            <!-- BARANGAY CLEARANCE -->

            <a href="services.php"
               class="service-card bg-white border border-gray-100 rounded-xl p-5 shadow-sm">

                <div class="w-12 h-12 rounded-xl bg-green900 text-white flex items-center justify-center text-2xl">
                    📄
                </div>

                <h3 class="font-bold text-green900 mt-5">
                    Barangay Clearance
                </h3>

                <p class="text-xs text-gray-500 leading-relaxed mt-2">
                    Proof of residency and good standing.
                </p>

                <div class="border-t border-gray-100 mt-5 pt-3 flex justify-between items-center">
                    <span class="text-xs text-gray-400">Fee</span>
                    <span class="font-extrabold text-gold">₱50</span>
                </div>

            </a>

            <!-- CERTIFICATE OF INDIGENCY -->

            <a href="services.php"
               class="service-card bg-white border border-gray-100 rounded-xl p-5 shadow-sm">

                <div class="w-12 h-12 rounded-xl bg-gold text-white flex items-center justify-center text-2xl">
                    🛡️
                </div>

                <h3 class="font-bold text-green900 mt-5">
                    Certificate of Indigency
                </h3>

                <p class="text-xs text-gray-500 leading-relaxed mt-2">
                    Certificate for financial assistance.
                </p>

                <div class="border-t border-gray-100 mt-5 pt-3 flex justify-between items-center">
                    <span class="text-xs text-gray-400">Fee</span>
                    <span class="font-extrabold text-gold">₱20</span>
                </div>

            </a>

            <!-- BLOTTER -->

            <a href="services.php"
               class="service-card bg-white border border-gray-100 rounded-xl p-5 shadow-sm">

                <div class="w-12 h-12 rounded-xl bg-green900 text-white flex items-center justify-center text-2xl">
                    📝
                </div>

                <h3 class="font-bold text-green900 mt-5">
                    Blotter
                </h3>

                <p class="text-xs text-gray-500 leading-relaxed mt-2">
                    Incident record and complaint filing.
                </p>

                <div class="border-t border-gray-100 mt-5 pt-3 flex justify-between items-center">
                    <span class="text-xs text-gray-400">Fee</span>
                    <span class="font-extrabold text-gold">₱100</span>
                </div>

            </a>

            <!-- BUSINESS PERMIT -->

            <a href="services.php"
               class="service-card bg-white border border-gray-100 rounded-xl p-5 shadow-sm">

                <div class="w-12 h-12 rounded-xl bg-green900 text-white flex items-center justify-center text-2xl">
                    🏪
                </div>

                <h3 class="font-bold text-green900 mt-5">
                    Business Permit
                </h3>

                <p class="text-xs text-gray-500 leading-relaxed mt-2">
                    New and renewal applications for businesses.
                </p>

                <div class="border-t border-gray-100 mt-5 pt-3 flex justify-between items-center">
                    <span class="text-xs text-gray-400">Fee</span>
                    <span class="font-extrabold text-gold">₱300</span>
                </div>

            </a>

            <!-- CEDULA -->

            <a href="services.php"
               class="service-card bg-white border border-gray-100 rounded-xl p-5 shadow-sm">

                <div class="w-12 h-12 rounded-xl bg-gold text-white flex items-center justify-center text-2xl">
                    🧾
                </div>

                <h3 class="font-bold text-green900 mt-5">
                    Cedula
                </h3>

                <p class="text-xs text-gray-500 leading-relaxed mt-2">
                    Community tax certificate.
                </p>

                <div class="border-t border-gray-100 mt-5 pt-3 flex justify-between items-center">
                    <span class="text-xs text-gray-400">Fee</span>
                    <span class="font-extrabold text-gold">₱50</span>
                </div>

            </a>

        </div>

    </section>


    <!-- ==========================================
         COMMUNITY INFORMATION BANNER
    =========================================== -->

    <section class="bg-green900 py-14">

        <div class="max-w-7xl mx-auto px-5 md:px-10">

            <div class="flex flex-col md:flex-row justify-between items-center gap-8">

                <div class="max-w-2xl">

                    <p class="text-gold2 text-xs font-bold uppercase section-label">
                        Serving Our Community
                    </p>

                    <h2 class="text-white text-3xl md:text-4xl font-extrabold mt-3 leading-tight">
                        Building a Better and Stronger Guindacpan Together.
                    </h2>

                    <p class="text-white/70 mt-4 leading-relaxed">
                        Stay informed about barangay programs, public services, community activities, and important local updates.
                    </p>

                </div>

                <a
                    href="announcements.php"
                    class="shrink-0 bg-gold hover:bg-gold2 text-white px-7 py-3.5 rounded-lg font-bold transition shadow-lg"
                >
                    Explore Announcements →
                </a>

            </div>

        </div>

    </section>


    <!-- ==========================================
         BARANGAY OFFICIALS
    =========================================== -->

    <section class="max-w-7xl mx-auto px-5 md:px-10 py-20">

        <!-- HEADING -->

        <div class="text-center max-w-2xl mx-auto">

            <p class="text-gold text-xs font-bold uppercase section-label">
                Public Leadership
            </p>

            <h2 class="text-3xl md:text-4xl font-extrabold text-green900 mt-3">
                Meet Our Barangay Officials
            </h2>

            <p class="text-gray-500 mt-4 leading-relaxed">
                Meet the people serving Barangay Guindacpan through leadership, public service, and community development.
            </p>

        </div>

        <!-- PUNONG BARANGAY -->

        <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden mt-12 max-w-4xl mx-auto">

            <div class="grid md:grid-cols-5">

                <div class="md:col-span-2">

                    <img
                        src="asset/me.png"
                        alt="Punong Barangay"
                        class="w-full h-72 md:h-full object-cover"
                    >

                </div>

                <div class="md:col-span-3 p-7 md:p-10 flex flex-col justify-center">

                    <span class="inline-block w-fit bg-green900 text-white text-xs font-bold px-4 py-2 rounded-full">
                        PUNONG BARANGAY
                    </span>

                    <h3 class="text-3xl font-extrabold text-green900 mt-4">
                        Hon. Processo P. Cajes
                    </h3>

                    <p class="text-sm text-gold font-semibold mt-2">
                        Barangay Captain
                    </p>

                    <div class="border-t border-gray-100 my-5"></div>

                    <div class="space-y-3 text-sm text-gray-600">

                        <p>
                            📅 <strong>Term:</strong> 2023–2026
                        </p>

                        <p>
                            📞 +63 917 555 1289
                        </p>

                        <p class="break-all">
                            ✉️ kapitan@brgyguindacpan.gov.ph
                        </p>

                    </div>

                    <p class="text-gray-500 text-sm leading-relaxed mt-5">
                        Chief Executive of Barangay Guindacpan. Oversees barangay administration, public safety, community programs, and local initiatives.
                    </p>

                    <span class="text-green700 text-xs font-bold mt-5">
                        Incumbent • Elected 2023
                    </span>

                </div>

            </div>

        </div>

        <!-- KAGAWAD MEMBERS -->

        <div class="mt-16">

            <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-3">

                <div>

                    <h3 class="text-2xl font-extrabold text-green900">
                        Barangay Council
                    </h3>

                    <p class="text-gray-500 text-sm mt-2">
                        Kagawad Members and Their Respective Committees
                    </p>

                </div>

                <a href="officials.php"
                   class="text-sm text-green900 font-bold hover:text-gold transition">
                    View All Officials →
                </a>

            </div>

            <!-- OFFICIALS GRID -->

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5 mt-8">

                <!-- KAGAWAD 1 -->

                <div class="official-card bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">

                    <img
                        src="asset/kagawad1.jpg"
                        alt="Kagawad Maria Santos"
                        class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-cream"
                    >

                    <h4 class="font-bold text-green900 mt-4">
                        Kagawad Maria Santos
                    </h4>

                    <span class="inline-block bg-cream text-green900 text-[10px] font-bold px-3 py-1 rounded-full mt-2">
                        Peace & Order
                    </span>

                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                        Public Safety, Curfew, Disaster Preparedness
                    </p>

                </div>

                <!-- KAGAWAD 2 -->

                <div class="official-card bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">

                    <img
                        src="asset/kagawad2.jpg"
                        alt="Kagawad Pedro Reyes"
                        class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-cream"
                    >

                    <h4 class="font-bold text-green900 mt-4">
                        Kagawad Pedro Reyes
                    </h4>

                    <span class="inline-block bg-cream text-green900 text-[10px] font-bold px-3 py-1 rounded-full mt-2">
                        Health
                    </span>

                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                        Health Programs, Sanitation, Nutrition
                    </p>

                </div>

                <!-- KAGAWAD 3 -->

                <div class="official-card bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">

                    <img
                        src="asset/kagawad3.jpg"
                        alt="Kagawad Liza Tan"
                        class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-cream"
                    >

                    <h4 class="font-bold text-green900 mt-4">
                        Kagawad Liza Tan
                    </h4>

                    <span class="inline-block bg-cream text-green900 text-[10px] font-bold px-3 py-1 rounded-full mt-2">
                        Education
                    </span>

                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                        Scholarships, Daycare, Learning Support
                    </p>

                </div>

                <!-- KAGAWAD 4 -->

                <div class="official-card bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">

                    <img
                        src="asset/kagawad4.jpg"
                        alt="Kagawad Carlos Lim"
                        class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-cream"
                    >

                    <h4 class="font-bold text-green900 mt-4">
                        Kagawad Carlos Lim
                    </h4>

                    <span class="inline-block bg-cream text-green900 text-[10px] font-bold px-3 py-1 rounded-full mt-2">
                        Infrastructure
                    </span>

                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                        Roads, Drainage, Facilities
                    </p>

                </div>

                <!-- KAGAWAD 5 -->

                <div class="official-card bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">

                    <img
                        src="asset/kagawad5.jpg"
                        alt="Kagawad Ana Rivera"
                        class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-cream"
                    >

                    <h4 class="font-bold text-green900 mt-4">
                        Kagawad Ana Rivera
                    </h4>

                    <span class="inline-block bg-cream text-green900 text-[10px] font-bold px-3 py-1 rounded-full mt-2">
                        Agriculture
                    </span>

                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                        Farmers Support, Livelihood, Coop Programs
                    </p>

                </div>

                <!-- KAGAWAD 6 -->

                <div class="official-card bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">

                    <img
                        src="asset/kagawad6.jpg"
                        alt="Kagawad Miguel Cruz"
                        class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-cream"
                    >

                    <h4 class="font-bold text-green900 mt-4">
                        Kagawad Miguel Cruz
                    </h4>

                    <span class="inline-block bg-gold text-white text-[10px] font-bold px-3 py-1 rounded-full mt-2">
                        Youth • SK Chairman
                    </span>

                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                        Youth Programs, Sports, Community Activities
                    </p>

                </div>

                <!-- KAGAWAD 7 -->

                <div class="official-card bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">

                    <img
                        src="asset/kagawad7.jpg"
                        alt="Kagawad Elena Bautista"
                        class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-cream"
                    >

                    <h4 class="font-bold text-green900 mt-4">
                        Kagawad Elena Bautista
                    </h4>

                    <span class="inline-block bg-cream text-green900 text-[10px] font-bold px-3 py-1 rounded-full mt-2">
                        Treasurer & Secretary
                    </span>

                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                        Budget, Financial Reports, Documentation
                    </p>

                </div>

            </div>

        </div>

    </section>


    <!-- ==========================================
         ANNOUNCEMENTS
    =========================================== -->

    <section class="bg-cream py-20">

        <div class="max-w-7xl mx-auto px-5 md:px-10">

            <!-- SECTION HEADER -->

            <div class="flex flex-col sm:flex-row justify-between sm:items-end gap-4">

                <div>

                    <p class="text-gold text-xs font-bold uppercase section-label">
                        Stay Updated
                    </p>

                    <h2 class="text-3xl md:text-4xl font-extrabold text-green900 mt-3">
                        Latest Announcements
                    </h2>

                    <p class="text-gray-600 mt-3 text-sm">
                        Important news, activities, and advisories from your barangay.
                    </p>

                </div>

                <a
                    href="announcements.php"
                    class="text-green900 font-bold text-sm hover:text-gold transition"
                >
                    View All Announcements →
                </a>

            </div>

            <!-- ANNOUNCEMENT CARDS -->

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mt-10">

                <!-- ANNOUNCEMENT 1 -->

                <article class="announcement-card bg-white rounded-xl shadow-sm overflow-hidden">

                    <div class="relative">

                        <img
                            src="https://images.unsplash.com/photo-1584982751601-97dcc096659c?w=800&q=80"
                            alt="Community medical mission"
                            class="w-full h-48 object-cover"
                        >

                        <span class="absolute top-4 left-4 bg-gold text-white text-[10px] font-bold px-3 py-1.5 rounded-full">
                            COMMUNITY
                        </span>

                    </div>

                    <div class="p-6">

                        <p class="text-xs text-gold font-bold">
                            OCTOBER 08, 2026
                        </p>

                        <h3 class="text-lg font-bold text-green900 mt-3">
                            Free Medical Mission
                        </h3>

                        <p class="text-sm text-gray-500 leading-relaxed mt-3">
                            Free check-up, dental services, and medicines. Open to all residents. 8AM–3PM at Barangay Hall.
                        </p>

                        <div class="border-t border-gray-100 mt-5 pt-4">

                            <a href="announcements.php"
                               class="text-green900 font-bold text-sm hover:text-gold transition">
                                Read More →
                            </a>

                        </div>

                    </div>

                </article>

                <!-- ANNOUNCEMENT 2 -->

                <article class="announcement-card bg-white rounded-xl shadow-sm overflow-hidden">

                    <div class="relative">

                        <img
                            src="https://images.unsplash.com/photo-1541675154750-0444c7d51e8e?w=800&q=80"
                            alt="Water service advisory"
                            class="w-full h-48 object-cover"
                        >

                        <span class="absolute top-4 left-4 bg-gold text-white text-[10px] font-bold px-3 py-1.5 rounded-full">
                            ADVISORY
                        </span>

                    </div>

                    <div class="p-6">

                        <p class="text-xs text-gold font-bold">
                            OCTOBER 07, 2026
                        </p>

                        <h3 class="text-lg font-bold text-green900 mt-3">
                            Water Service Interruption Notice
                        </h3>

                        <p class="text-sm text-gray-500 leading-relaxed mt-3">
                            Scheduled water interruption on October 18, 8PM–5AM for pipe maintenance in Purok 1 and 2.
                        </p>

                        <div class="border-t border-gray-100 mt-5 pt-4">

                            <a href="announcements.php"
                               class="text-green900 font-bold text-sm hover:text-gold transition">
                                Read More →
                            </a>

                        </div>

                    </div>

                </article>

                <!-- ANNOUNCEMENT 3 -->

                <article class="announcement-card bg-white rounded-xl shadow-sm overflow-hidden">

                    <div class="relative">

                        <img
                            src="https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800&q=80"
                            alt="Barangay fiesta celebration"
                            class="w-full h-48 object-cover"
                        >

                        <span class="absolute top-4 left-4 bg-gold text-white text-[10px] font-bold px-3 py-1.5 rounded-full">
                            EVENT
                        </span>

                    </div>

                    <div class="p-6">

                        <p class="text-xs text-gold font-bold">
                            OCTOBER 06, 2026
                        </p>

                        <h3 class="text-lg font-bold text-green900 mt-3">
                            Barangay Fiesta Celebration
                        </h3>

                        <p class="text-sm text-gray-500 leading-relaxed mt-3">
                            Join us for the Guindacpan Fiesta featuring a parade, mass, and community night. Everyone is welcome!
                        </p>

                        <div class="border-t border-gray-100 mt-5 pt-4">

                            <a href="announcements.php"
                               class="text-green900 font-bold text-sm hover:text-gold transition">
                                Read More →
                            </a>

                        </div>

                    </div>

                </article>

            </div>

        </div>

    </section>


    <!-- ==========================================
         CONTACT AND LOCATION
    =========================================== -->

    <section class="max-w-7xl mx-auto px-5 md:px-10 py-20">

        <div class="grid md:grid-cols-2 gap-10 items-center">

            <!-- CONTACT INTRODUCTION -->

            <div>

                <p class="text-gold text-xs font-bold uppercase section-label">
                    Get In Touch
                </p>

                <h2 class="text-3xl md:text-4xl font-extrabold text-green900 mt-3">
                    We're Here to Serve You.
                </h2>

                <p class="text-gray-500 leading-relaxed mt-5">
                    For questions, concerns, or barangay-related assistance, you may contact or visit the Barangay Guindacpan office during official working hours.
                </p>

                <a
                    href="#"
                    class="inline-block bg-green900 hover:bg-green800 text-white px-7 py-3 rounded-lg font-bold text-sm mt-7 transition"
                >
                    Explore Our Services →
                </a>

            </div>

            <!-- CONTACT DETAILS -->

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 md:p-9">

                <h3 class="text-xl font-extrabold text-green900 mb-6">
                    Barangay Contact Information
                </h3>

                <div class="space-y-6">

                    <!-- ADDRESS -->

                    <div class="flex gap-4 items-start">

                        <div class="w-11 h-11 rounded-lg bg-cream flex items-center justify-center text-xl shrink-0">
                            📍
                        </div>

                        <div>
                            <p class="font-bold text-green900">
                                Office Address
                            </p>

                            <p class="text-sm text-gray-500 mt-1">
                                Barangay Hall, Guindacpan, Talibon, Bohol 6325
                            </p>
                        </div>

                    </div>

                    <!-- TELEPHONE -->

                    <div class="flex gap-4 items-start">

                        <div class="w-11 h-11 rounded-lg bg-cream flex items-center justify-center text-xl shrink-0">
                            📞
                        </div>

                        <div>
                            <p class="font-bold text-green900">
                                Telephone
                            </p>

                            <p class="text-sm text-gray-500 mt-1">
                                (038) 555-0123
                            </p>
                        </div>

                    </div>

                    <!-- EMAIL -->

                    <div class="flex gap-4 items-start">

                        <div class="w-11 h-11 rounded-lg bg-cream flex items-center justify-center text-xl shrink-0">
                            ✉️
                        </div>

                        <div>
                            <p class="font-bold text-green900">
                                Email Address
                            </p>

                            <p class="text-sm text-gray-500 mt-1 break-all">
                                brgy.guindacpan.talibon@gmail.com
                            </p>
                        </div>

                    </div>

                    <!-- OFFICE HOURS -->

                    <div class="flex gap-4 items-start">

                        <div class="w-11 h-11 rounded-lg bg-cream flex items-center justify-center text-xl shrink-0">
                            🕒
                        </div>

                        <div>
                            <p class="font-bold text-green900">
                                Office Hours
                            </p>

                            <p class="text-sm text-gray-500 mt-1">
                                Monday – Friday, 8:00 AM – 5:00 PM
                            </p>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ==========================================
         FOOTER
    =========================================== -->

    <footer class="bg-green900 text-white">

        <!-- FOOTER MAIN CONTENT -->

        <div class="max-w-7xl mx-auto px-5 md:px-10 py-14">

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-10">

                <!-- BRAND -->

                <div class="lg:col-span-2">

                    <div class="flex items-center gap-3">

                        <img
                            src="asset/logo.png"
                            alt="Barangay Logo"
                            class="w-14 h-14 rounded-full border-2 border-gold object-cover"
                        >

                        <div>
                            <h3 class="font-extrabold text-lg">
                                BARANGAY GUINDACPAN
                            </h3>

                            <p class="text-white/60 text-sm">
                                Talibon, Bohol • Philippines
                            </p>
                        </div>

                    </div>

                    <p class="text-white/65 text-sm leading-relaxed mt-5 max-w-sm">
                        Dedicated to providing accessible public services, transparent governance, and a stronger community for every resident of Guindacpan.
                    </p>

                </div>

                <!-- QUICK LINKS -->

                <div>

                    <h3 class="font-bold text-lg">
                        Quick Links
                    </h3>

                    <ul class="space-y-3 text-sm text-white/65 mt-5">

                        <li>
                            <a href="main.php" class="hover:text-gold2 transition">
                                Home
                            </a>
                        </li>

                        <li>
                            <a href="services.php" class="hover:text-gold2 transition">
                                Barangay Services
                            </a>
                        </li>

                        <li>
                            <a href="officials.php" class="hover:text-gold2 transition">
                                Barangay Officials
                            </a>
                        </li>

                        <li>
                            <a href="announcements.php" class="hover:text-gold2 transition">
                                Announcements
                            </a>
                        </li>

                        <li>
                            <a href="track.php" class="hover:text-gold2 transition">
                                Track Request
                            </a>
                        </li>

                    </ul>

                </div>

                <!-- CONTACT -->

                <div>

                    <h3 class="font-bold text-lg">
                        Contact Us
                    </h3>

                    <ul class="space-y-4 text-sm text-white/65 mt-5">

                        <li>
                            📍 Guindacpan, Talibon, Bohol
                        </li>

                        <li>
                            📞 (038) 555-0123
                        </li>

                        <li class="break-all">
                            ✉️ brgy.guindacpan.talibon@gmail.com
                        </li>

                        <li>
                            f @BrgyGuindacpanTalibon
                        </li>

                    </ul>

                </div>

            </div>

        </div>

        <!-- COPYRIGHT -->

        <div class="bg-black/20 border-t border-white/10">

            <div class="max-w-7xl mx-auto px-5 md:px-10 py-5 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-white/60">

                <p>
                    © 2026 Barangay Guindacpan, Talibon, Bohol. All rights reserved.
                </p>

                <div class="flex gap-4">

                    <a href="privacy.php" class="hover:text-gold2 transition">
                        Privacy Policy
                    </a>

                    <a href="terms.php" class="hover:text-gold2 transition">
                        Terms of Service
                    </a>

                    <a href="admin/dashboard.php" class="hover:text-gold2 transition">
                        Secretary Portal
                    </a>

                    <a href="bhw/create.php" class="hover:text-gold2 transition">
                        BHW Portal
                    </a>

                    <a href="midwife/CreatAdmin.php" class="hover:text-gold2 transition">
                      Midwife
                    </a>

                </div>

            </div>

        </div>

    </footer>


    <!-- ==========================================
         RESIDENT CREATE ACCOUNT MODAL
         UPDATED FORM DESIGN ONLY
    =========================================== -->

    <div
        id="residentLogin"
        class="hidden fixed inset-0 z-[100] bg-black/70 items-center justify-center p-3 sm:p-5"
        role="dialog"
        aria-modal="true"
        aria-labelledby="residentLoginTitle"
    >

        <div class="relative w-full max-w-2xl max-h-[94vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">

            <!-- HEADER -->

            <div class="relative overflow-hidden bg-green900 px-6 py-8 sm:px-10">

                <div class="absolute -right-12 -top-16 h-48 w-48 rounded-full border-[25px] border-white/5"></div>

                <div class="absolute -bottom-20 -left-10 h-40 w-40 rounded-full bg-gold/10"></div>

                <!-- CLOSE BUTTON -->

                <button
                    type="button"
                    id="closeLogin"
                    class="absolute right-5 top-5 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-2xl text-white transition hover:bg-white/20"
                    aria-label="Close registration form"
                >
                    &times;
                </button>

                <!-- FORM HEADER -->

                <div class="relative z-10 flex flex-col items-center text-center">

                    <div class="mb-4 rounded-full border-4 border-gold bg-white p-1 shadow-lg">

                        <img
                            src="asset/logo.png"
                            alt="Barangay Guindacpan Logo"
                            class="h-20 w-20 rounded-full object-cover"
                        >

                    </div>

                    <span class="mb-2 rounded-full border border-white/20 bg-white/10 px-4 py-1 text-[10px] font-bold uppercase tracking-[3px] text-gold2">
                        Barangay Guindacpan
                    </span>

                    <h2
                        id="residentLoginTitle"
                        class="text-2xl font-extrabold tracking-wide text-white sm:text-3xl"
                    >
                        CREATE YOUR ACCOUNT
                    </h2>

                    <p class="mt-2 max-w-md text-sm leading-relaxed text-white/70">
                        Register as a resident to access barangay services and manage your requests online.
                    </p>

                </div>

            </div>

            <!-- FORM CONTENT -->

            <div class="px-5 py-7 sm:px-10 sm:py-9">

                <!-- FORM INTRODUCTION -->

                <div class="mb-7 flex items-start gap-3 rounded-xl border border-green-100 bg-green-50 p-4">

                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green900 text-white">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>

                    <div>

                        <h3 class="font-bold text-green900">
                            Resident Registration
                        </h3>

                        <p class="mt-1 text-xs leading-relaxed text-gray-500">
                            Please provide accurate information. All required fields must be completed.
                        </p>

                    </div>

                </div>

                <!-- REGISTRATION FORM -->

                <form
                    id="residentRegistrationForm"
                    action="LogIn.php"
                    method="POST"
                    class="space-y-7"
                >

                    <!-- PERSONAL INFORMATION -->

                    <div>

                        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">

                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-100 text-green900">
                                <i class="fa-solid fa-id-card"></i>
                            </div>

                            <div>

                                <h3 class="font-bold text-gray-800">
                                    Personal Information
                                </h3>

                                <p class="text-xs text-gray-400">
                                    Your basic resident details
                                </p>

                            </div>

                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                            <!-- FULL NAME -->

                            <div class="sm:col-span-2">

                                <label for="residentFullName" class="mb-2 block text-sm font-semibold text-gray-700">
                                    Full Name <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">

                                    <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                                    <input
                                        id="residentFullName"
                                        name="fullName"
                                        type="text"
                                        autocomplete="name"
                                        required
                                        placeholder="Enter your complete name"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-3.5 pl-11 pr-4 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-green-700 focus:bg-white focus:ring-4 focus:ring-green-700/10"
                                    >

                                </div>

                            </div>

                            <!-- USERNAME -->

                            <div>

                                <label for="residentUsername" class="mb-2 block text-sm font-semibold text-gray-700">
                                    Username <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">

                                    <i class="fa-solid fa-at absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                                    <input
                                        id="residentUsername"
                                        name="username"
                                        type="text"
                                        autocomplete="username"
                                        required
                                        placeholder="Create a username"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-3.5 pl-11 pr-4 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-green-700 focus:bg-white focus:ring-4 focus:ring-green-700/10"
                                    >

                                </div>

                            </div>

                            <!-- BIRTHDAY -->

                            <div>

                                <label for="residentBirth" class="mb-2 block text-sm font-semibold text-gray-700">
                                    Birthday <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">

                                    <i class="fa-solid fa-calendar-days absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                                    <input
                                        id="residentBirth"
                                        name="birthday"
                                        type="date"
                                        required
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-3.5 pl-11 pr-4 text-sm text-gray-700 outline-none transition focus:border-green-700 focus:bg-white focus:ring-4 focus:ring-green-700/10"
                                    >

                                </div>

                            </div>

                            <!-- ADDRESS -->

                            <div class="sm:col-span-2">

                                <label for="residentAddress" class="mb-2 block text-sm font-semibold text-gray-700">
                                    Purok / Complete Address <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">

                                    <i class="fa-solid fa-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                                    <input
                                        id="residentAddress"
                                        name="address"
                                        type="text"
                                        required
                                        placeholder="Enter your purok and complete address"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-3.5 pl-11 pr-4 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-green-700 focus:bg-white focus:ring-4 focus:ring-green-700/10"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- ACCOUNT SECURITY -->

                    <div>

                        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">

                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>

                            <div>

                                <h3 class="font-bold text-gray-800">
                                    Account Security
                                </h3>

                                <p class="text-xs text-gray-400">
                                    Create a secure password for your account
                                </p>

                            </div>

                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                            <!-- PASSWORD -->

                            <div>

                                <label for="residentPassword" class="mb-2 block text-sm font-semibold text-gray-700">
                                    Password <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">

                                    <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                                    <input
                                        id="residentPassword"
                                        name="password"
                                        type="password"
                                        autocomplete="new-password"
                                        minlength="8"
                                        required
                                        placeholder="Minimum 8 characters"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-3.5 pl-11 pr-4 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-green-700 focus:bg-white focus:ring-4 focus:ring-green-700/10"
                                    >

                                </div>

                                <p class="mt-2 text-[11px] text-gray-400">
                                    <i class="fa-solid fa-circle-info mr-1"></i>
                                    Use at least 8 characters.
                                </p>

                            </div>

                            <!-- CONFIRM PASSWORD -->

                            <div>

                                <label for="residentConfirm" class="mb-2 block text-sm font-semibold text-gray-700">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">

                                    <i class="fa-solid fa-shield-halved absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                                    <input
                                        id="residentConfirm"
                                        name="confirm"
                                        type="password"
                                        autocomplete="new-password"
                                        minlength="8"
                                        required
                                        placeholder="Re-enter your password"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-3.5 pl-11 pr-4 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-green-700 focus:bg-white focus:ring-4 focus:ring-green-700/10"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- PRIVACY NOTICE -->

                    <div class="flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50 p-4">

                        <i class="fa-solid fa-circle-info mt-0.5 text-amber-600"></i>

                        <p class="text-xs leading-relaxed text-gray-600">
                            Your registration information will be used for barangay resident account processing. Your account may require verification by the barangay office.
                        </p>

                    </div>


                    <!-- CREATE ACCOUNT BUTTON -->

                    <div class="space-y-3">

                        <button
                            type="submit"
                            name="register"
                            class="group flex w-full items-center justify-center gap-3 rounded-xl bg-green900 py-4 text-sm font-extrabold tracking-wide text-white shadow-lg shadow-green-900/20 transition hover:-translate-y-0.5 hover:bg-green800 hover:shadow-xl"
                        >

                            <i class="fa-solid fa-user-plus transition group-hover:scale-110"></i>

                            CREATE ACCOUNT

                            <i class="fa-solid fa-arrow-right text-xs transition group-hover:translate-x-1"></i>

                        </button>

                        <p class="text-center text-xs text-gray-400">
                            By registering, you agree to provide accurate information.
                        </p>

                    </div>

                </form>

            </div>

            <!-- MODAL FOOTER -->

            <div class="border-t border-gray-100 bg-gray-50 px-6 py-4 text-center">

                <div class="flex items-center justify-center gap-2 text-xs text-gray-500">

                    <i class="fa-solid fa-landmark text-green900"></i>

                    <span>Official Resident Registration Portal</span>

                </div>

                <p class="mt-1 text-[10px] text-gray-400">
                    Barangay Guindacpan • Talibon, Bohol
                </p>

            </div>

        </div>

    </div>

    <!-- CREATE ACCOUNT MODAL CONTROLS -->

   
</body>
<script src="barangay.js"></script>

</html>
