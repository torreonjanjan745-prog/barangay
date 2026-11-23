
<?php
$pageTitle = "Privacy Policy";
$barangayName = "Barangay Guindacpan";
$lastUpdated = "November 22, 2026";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($barangayName) ?></title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        html { scroll-behavior: smooth; }

        body {
            font-family: Arial, sans-serif;
        }

        .policy-section {
            scroll-margin-top: 100px;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800">

<!-- HEADER -->
<header class="bg-slate-950 text-white sticky top-0 z-50 shadow-lg">
    <div class="max-w-7xl mx-auto px-5 py-4 flex justify-between items-center">

        <a href="main.php" class="flex items-center gap-3">
            <img
                src="asset/logo.jpg"
                alt="Barangay Guindacpan Logo"
                class="w-11 h-11 object-contain rounded-xl bg-white p-1"
            >

            <div>
                <h1 class="font-bold text-sm md:text-base">
                    BARANGAY GUINDACPAN
                </h1>
                <p class="text-xs text-slate-400">
                    Official Barangay Website
                </p>
            </div>
        </a>

        <a href="main.php"
           class="text-sm text-slate-300 hover:text-green-400 transition">
            ← Back to Home
        </a>
    </div>
</header>

<!-- HERO -->
<section class="bg-slate-950 text-white py-16 md:py-24">
    <div class="max-w-5xl mx-auto px-5 text-center">

        <div class="inline-block bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-2 rounded-full text-xs font-semibold mb-6">
            DATA PRIVACY AND PROTECTION
        </div>

        <h2 class="text-3xl md:text-5xl font-bold">
            Privacy <span class="text-green-500">Policy</span>
        </h2>

        <p class="text-slate-400 max-w-2xl mx-auto mt-6 leading-relaxed">
            Your privacy matters to us. Learn how Barangay Guindacpan
            collects, uses, stores, and protects your personal information.
        </p>

        <p class="text-xs text-slate-500 mt-6">
            Last Updated: <?= htmlspecialchars($lastUpdated) ?>
        </p>

    </div>
</section>

<!-- MAIN -->
<main class="max-w-7xl mx-auto px-5 py-12 md:py-16">

    <div class="grid lg:grid-cols-4 gap-10">

        <!-- TABLE OF CONTENTS -->
        <aside class="lg:col-span-1">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm lg:sticky lg:top-28">

                <h3 class="font-bold text-slate-900 mb-5">
                    Privacy Policy
                </h3>

                <nav class="space-y-3 text-sm">
                    <a href="#introduction" class="block text-slate-600 hover:text-green-600">1. Introduction</a>
                    <a href="#controller" class="block text-slate-600 hover:text-green-600">2. Data Controller</a>
                    <a href="#collection" class="block text-slate-600 hover:text-green-600">3. Information Collected</a>
                    <a href="#purpose" class="block text-slate-600 hover:text-green-600">4. Purpose of Collection</a>
                    <a href="#legal" class="block text-slate-600 hover:text-green-600">5. Legal Basis</a>
                    <a href="#storage" class="block text-slate-600 hover:text-green-600">6. Storage and Retention</a>
                    <a href="#security" class="block text-slate-600 hover:text-green-600">7. Data Security</a>
                    <a href="#sharing" class="block text-slate-600 hover:text-green-600">8. Data Sharing</a>
                    <a href="#rights" class="block text-slate-600 hover:text-green-600">9. Your Privacy Rights</a>
                    <a href="#cookies" class="block text-slate-600 hover:text-green-600">10. Cookies and Sessions</a>
                    <a href="#children" class="block text-slate-600 hover:text-green-600">11. Children's Information</a>
                    <a href="#breach" class="block text-slate-600 hover:text-green-600">12. Data Breaches</a>
                    <a href="#changes" class="block text-slate-600 hover:text-green-600">13. Policy Updates</a>
                    <a href="#contact" class="block text-slate-600 hover:text-green-600">14. Contact Us</a>
                </nav>

            </div>
        </aside>

        <!-- CONTENT -->
        <div class="lg:col-span-3 space-y-8">

            <!-- INTRODUCTION -->
            <section id="introduction" class="policy-section bg-green-50 border border-green-200 rounded-2xl p-6 md:p-8">

                <h2 class="text-xl font-bold text-green-900 mb-3">
                    Your Privacy Is Important to Us
                </h2>

                <p class="text-sm text-green-900/80 leading-7">
                    Barangay Guindacpan is committed to respecting and
                    protecting the privacy of residents and individuals
                    who use its online services. This Privacy Policy
                    explains how personal information is handled when
                    using the Barangay Guindacpan Management System.
                </p>

                <p class="text-sm text-green-900/80 leading-7 mt-3">
                    This policy is guided by Republic Act No. 10173,
                    otherwise known as the Data Privacy Act of 2012,
                    its Implementing Rules and Regulations, and
                    applicable issuances of the National Privacy Commission.
                </p>

            </section>

            <!-- DATA CONTROLLER -->
            <section id="controller" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    2. Personal Information Controller
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The Barangay Government of Guindacpan is responsible
                    for determining the purposes and means of processing
                    personal information collected through its official
                    management system, subject to applicable law.
                </p>

                <div class="mt-5 bg-slate-50 rounded-xl p-5">
                    <h3 class="font-bold text-slate-900 mb-2">
                        Barangay Guindacpan
                    </h3>
                    <p class="text-sm text-slate-600">
                        Barangay Hall, Guindacpan, Philippines
                    </p>
                    <p class="text-xs text-slate-500 mt-3">
                        Official privacy contact details must be supplied
                        and verified by the barangay.
                    </p>
                </div>

            </section>

            <!-- INFORMATION COLLECTED -->
            <section id="collection" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    3. Personal Information We Collect
                </h2>

                <p class="text-sm text-slate-600 leading-7 mb-5">
                    Depending on the services used, the system may collect
                    the following categories of information:
                </p>

                <div class="grid sm:grid-cols-2 gap-4">

                    <div class="bg-slate-50 rounded-xl p-5">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Personal Information
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Full name, birthday, username, and residential address.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-5">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Account Information
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Account identifiers, password hashes, and account status.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-5">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Profile Information
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Profile photographs and details voluntarily
                            updated by users.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-5">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Service Requests
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Document request types, purposes, tracking
                            codes, and request history.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-5">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Technical Information
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Session information and necessary security
                            or access records, where collected.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-5">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Supporting Documents
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Documents submitted when required for a
                            particular barangay service.
                        </p>
                    </div>

                </div>

                <p class="text-xs text-slate-500 mt-5 leading-6">
                    Only information necessary and proportionate to
                    the requested service should be collected.
                </p>

            </section>

            <!-- PURPOSE -->
            <section id="purpose" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    4. How We Use Your Information
                </h2>

                <p class="text-sm text-slate-600 leading-7 mb-4">
                    Personal information may be processed for the following
                    legitimate barangay purposes:
                </p>

                <ul class="list-disc pl-6 space-y-2 text-sm text-slate-600 leading-7">
                    <li>Resident registration and identity verification.</li>
                    <li>Account authentication and management.</li>
                    <li>Processing barangay certificates and clearances.</li>
                    <li>Monitoring and tracking service requests.</li>
                    <li>Communicating important service updates.</li>
                    <li>Maintaining official barangay records.</li>
                    <li>Preventing unauthorized access and fraudulent activity.</li>
                    <li>Complying with legal and government obligations.</li>
                </ul>

            </section>

            <!-- LEGAL BASIS -->
            <section id="legal" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    5. Legal Basis for Processing
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The processing of personal information must have
                    an applicable lawful basis under the Data Privacy
                    Act of 2012. Depending on the specific activity,
                    this may include compliance with a legal obligation,
                    performance of a public authority's lawful functions,
                    or consent where consent is the appropriate legal basis.
                </p>

                <p class="text-sm text-slate-600 leading-7 mt-3">
                    The barangay should identify and communicate the
                    applicable legal basis for each processing activity.
                    Consent should not be treated as the automatic legal
                    basis for every government service.
                </p>

            </section>

            <!-- STORAGE -->
            <section id="storage" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    6. Data Storage and Retention
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    Personal information is stored in the barangay's
                    authorized information systems and records.
                    Information should only be retained for as long
                    as necessary for its declared purpose or as required
                    by applicable laws, regulations, and records
                    retention requirements.
                </p>

                <p class="text-sm text-slate-600 leading-7 mt-3">
                    When information is no longer necessary and no
                    retention obligation applies, it should be securely
                    disposed of or anonymized in accordance with
                    approved procedures.
                </p>

            </section>

            <!-- SECURITY -->
            <section id="security" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    7. Data Security and Protection
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The barangay should implement reasonable
                    organizational, physical, and technical safeguards
                    to protect personal information.
                </p>

                <div class="grid sm:grid-cols-2 gap-4 mt-5">

                    <div class="border border-slate-200 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Account Security
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Password hashing, secure authentication,
                            and controlled account access.
                        </p>
                    </div>

                    <div class="border border-slate-200 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Access Control
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Limiting access to authorized personnel
                            based on official responsibilities.
                        </p>
                    </div>

                    <div class="border border-slate-200 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Data Protection
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Appropriate safeguards for resident
                            information and uploaded files.
                        </p>
                    </div>

                    <div class="border border-slate-200 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 mb-2">
                            Security Monitoring
                        </h3>
                        <p class="text-sm text-slate-600 leading-6">
                            Appropriate monitoring and response
                            procedures for security incidents.
                        </p>
                    </div>

                </div>

            </section>

            <!-- SHARING -->
            <section id="sharing" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    8. Disclosure and Sharing of Information
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The barangay does not intend to make residents'
                    personal information publicly available.
                    Information may only be disclosed to authorized
                    personnel, government authorities, or other
                    authorized recipients when necessary and permitted
                    by law.
                </p>

                <p class="text-sm text-slate-600 leading-7 mt-3">
                    Appropriate safeguards and confidentiality
                    requirements should be applied whenever
                    information is shared.
                </p>

            </section>

            <!-- RIGHTS -->
            <section id="rights" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    9. Your Rights as a Data Subject
                </h2>

                <p class="text-sm text-slate-600 leading-7 mb-5">
                    Under the Data Privacy Act, you may exercise
                    applicable rights concerning your personal information,
                    subject to legal limitations.
                </p>

                <div class="space-y-4">

                    <div>
                        <h3 class="font-semibold text-slate-900">Right to Be Informed</h3>
                        <p class="text-sm text-slate-600 leading-6">
                            To know how and why your information is collected and processed.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-900">Right to Access</h3>
                        <p class="text-sm text-slate-600 leading-6">
                            To request access to your personal information.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-900">Right to Rectification</h3>
                        <p class="text-sm text-slate-600 leading-6">
                            To request correction of inaccurate or incomplete information.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-900">Right to Object</h3>
                        <p class="text-sm text-slate-600 leading-6">
                            To object to certain processing activities when legally applicable.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-900">Right to Erasure or Blocking</h3>
                        <p class="text-sm text-slate-600 leading-6">
                            To request erasure or blocking when the legal requirements are met.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-900">Right to File a Complaint</h3>
                        <p class="text-sm text-slate-600 leading-6">
                            To lodge a complaint with the National Privacy Commission when appropriate.
                        </p>
                    </div>

                </div>

                <p class="text-sm text-slate-600 leading-7 mt-5">
                    The exercise of these rights may be subject to
                    applicable laws, official records requirements,
                    and other lawful restrictions.
                </p>

            </section>

            <!-- COOKIES -->
            <section id="cookies" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    10. Cookies and Session Information
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The website may use session cookies to maintain
                    authenticated user sessions, support account security,
                    and protect access to authorized services.
                    These technical mechanisms should not be used to
                    collect unnecessary personal information.
                </p>

            </section>

            <!-- CHILDREN -->
            <section id="children" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    11. Children's Personal Information
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    Where personal information concerning minors is
                    processed, the barangay should apply appropriate
                    safeguards and comply with applicable legal
                    requirements, including rules concerning parental
                    authority or lawful representation where necessary.
                </p>

            </section>

            <!-- BREACH -->
            <section id="breach" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    12. Personal Data Breaches
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    In the event of a personal data breach, the barangay
                    should investigate, document, and respond to the
                    incident in accordance with its incident response
                    procedures and applicable National Privacy Commission
                    requirements, including notification obligations
                    where applicable.
                </p>

            </section>

            <!-- POLICY CHANGES -->
            <section id="changes" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    13. Changes to This Privacy Policy
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    This Privacy Policy may be updated to reflect
                    changes in barangay services, information processing
                    practices, security procedures, or applicable laws.
                    The latest revision date will be displayed on
                    this page.
                </p>

            </section>

            <!-- CONTACT -->
            <section id="contact" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold mb-4">
                    14. Contact Us
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    If you have questions, requests, or concerns about
                    your personal information, please contact the
                    authorized privacy representative of Barangay Guindacpan.
                </p>

                <div class="mt-5 bg-slate-50 rounded-xl p-5">

                    <h3 class="font-bold text-slate-900 mb-2">
                        Barangay Guindacpan
                    </h3>

                    <p class="text-sm text-slate-600">
                        Barangay Hall, Guindacpan, Philippines
                    </p>

                    <p class="text-sm text-slate-600 mt-2">
                        Privacy Contact: To be officially designated
                    </p>

                    <p class="text-sm text-slate-600 mt-2">
                        Email / Telephone: To be officially provided
                    </p>

                </div>

            </section>

            <!-- DISCLAIMER -->
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6">

                <h3 class="font-bold text-amber-900 mb-3">
                    Important Privacy Notice
                </h3>

                <p class="text-sm text-amber-900/80 leading-7">
                    This Privacy Policy is a proposed template for
                    Barangay Guindacpan. It must be reviewed and approved
                    by the barangay before official use. Actual data
                    processing activities, lawful bases, retention
                    periods, security measures, and privacy contact
                    information must be verified. Publication of this
                    page alone does not establish compliance with
                    the Data Privacy Act of 2012.
                </p>

            </div>

            <!-- ACTIONS -->
            <div class="flex flex-wrap gap-3 pt-3">

                <a href="main.php"
                   class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-semibold text-sm transition">
                    ← Back to Homepage
                </a>

                <button onclick="window.print()"
                        class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold text-sm transition">
                    Print Privacy Policy
                </button>

            </div>

        </div>
    </div>
</main>

<!-- FOOTER -->
<footer class="bg-slate-950 text-slate-400 py-8">
    <div class="max-w-7xl mx-auto px-5 text-center">

        <h3 class="text-white font-bold">
            Barangay Guindacpan
        </h3>

        <p class="text-xs mt-2">
            Barangay Guindacpan Management System
        </p>

        <p class="text-xs mt-4">
            © <?= date("Y") ?> Barangay Guindacpan. All rights reserved.
        </p>

    </div>
</footer>

</body>
</html>
