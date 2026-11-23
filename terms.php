
<?php
$pageTitle = "Terms and Conditions & Privacy Policy";
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
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, sans-serif;
        }

        .policy-section {
            scroll-margin-top: 100px;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800">

<!-- NAVIGATION -->
<header class="bg-slate-950 text-white sticky top-0 z-50 shadow-lg">

    <div class="max-w-7xl mx-auto px-5 py-4 flex items-center justify-between">

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

        <div class="inline-flex items-center gap-2 bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-2 rounded-full text-xs font-semibold mb-6">
            OFFICIAL WEBSITE POLICIES
        </div>

        <h2 class="text-3xl md:text-5xl font-bold leading-tight">
            Terms and Conditions
            <span class="text-green-500">& Privacy Policy</span>
        </h2>

        <p class="text-slate-400 max-w-2xl mx-auto mt-6 leading-relaxed">
            Please read these terms carefully before using the
            Barangay Guindacpan Management System and online services.
        </p>

        <p class="text-xs text-slate-500 mt-6">
            Last Updated: <?= htmlspecialchars($lastUpdated) ?>
        </p>

    </div>

</section>

<!-- MAIN CONTENT -->
<main class="max-w-7xl mx-auto px-5 py-12 md:py-16">

    <div class="grid lg:grid-cols-4 gap-10">

        <!-- TABLE OF CONTENTS -->
        <aside class="lg:col-span-1">

            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm lg:sticky lg:top-28">

                <h3 class="font-bold text-slate-900 mb-5">
                    On This Page
                </h3>

                <nav class="space-y-3 text-sm">

                    <a href="#terms" class="block text-slate-600 hover:text-green-600">
                        1. Terms and Conditions
                    </a>

                    <a href="#acceptance" class="block text-slate-600 hover:text-green-600">
                        2. Acceptance of Terms
                    </a>

                    <a href="#eligibility" class="block text-slate-600 hover:text-green-600">
                        3. User Eligibility
                    </a>

                    <a href="#accounts" class="block text-slate-600 hover:text-green-600">
                        4. User Accounts
                    </a>

                    <a href="#services" class="block text-slate-600 hover:text-green-600">
                        5. Barangay Services
                    </a>

                    <a href="#responsibilities" class="block text-slate-600 hover:text-green-600">
                        6. User Responsibilities
                    </a>

                    <a href="#privacy" class="block text-slate-600 hover:text-green-600">
                        7. Privacy Policy
                    </a>

                    <a href="#collection" class="block text-slate-600 hover:text-green-600">
                        8. Information Collected
                    </a>

                    <a href="#usage" class="block text-slate-600 hover:text-green-600">
                        9. Use of Information
                    </a>

                    <a href="#protection" class="block text-slate-600 hover:text-green-600">
                        10. Data Protection
                    </a>

                    <a href="#sharing" class="block text-slate-600 hover:text-green-600">
                        11. Data Sharing
                    </a>

                    <a href="#rights" class="block text-slate-600 hover:text-green-600">
                        12. Data Privacy Rights
                    </a>

                    <a href="#cookies" class="block text-slate-600 hover:text-green-600">
                        13. Cookies and Security
                    </a>

                    <a href="#changes" class="block text-slate-600 hover:text-green-600">
                        14. Policy Changes
                    </a>

                    <a href="#contact" class="block text-slate-600 hover:text-green-600">
                        15. Contact Information
                    </a>

                </nav>

            </div>

        </aside>

        <!-- POLICY CONTENT -->
        <div class="lg:col-span-3 space-y-8">

            <!-- INTRODUCTION -->
            <div class="bg-green-50 border border-green-200 rounded-2xl p-6 md:p-8">

                <h3 class="text-lg font-bold text-green-900 mb-3">
                    Welcome to Barangay Guindacpan
                </h3>

                <p class="text-sm text-green-900/80 leading-7">
                    The Barangay Guindacpan Management System is intended
                    to provide residents with convenient access to
                    barangay information, resident registration,
                    document requests, and request tracking.
                    These policies explain the rules for using the
                    website and how personal information may be handled.
                </p>

            </div>

            <!-- TERMS -->
            <section id="terms" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-2xl font-bold text-slate-900 mb-5">
                    1. Terms and Conditions
                </h2>

                <p class="text-slate-600 leading-7 text-sm">
                    These Terms and Conditions govern the use of the
                    Barangay Guindacpan website and its online services.
                    By accessing or using this website, users agree
                    to comply with the rules and guidelines stated herein.
                </p>

            </section>

            <!-- ACCEPTANCE -->
            <section id="acceptance" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    2. Acceptance of Terms
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    By creating an account, submitting information,
                    requesting barangay documents, or using any available
                    website feature, you acknowledge that you have read
                    and understood these Terms and Conditions and the
                    Privacy Policy.
                </p>

                <p class="text-sm text-slate-600 leading-7 mt-3">
                    If you do not agree with these policies, please
                    discontinue using the online services.
                </p>

            </section>

            <!-- ELIGIBILITY -->
            <section id="eligibility" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    3. User Eligibility
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The online services are primarily intended for
                    residents of Barangay Guindacpan and individuals
                    authorized to transact with the barangay.
                    Users must provide accurate information and comply
                    with applicable laws and barangay procedures.
                </p>

            </section>

            <!-- ACCOUNTS -->
            <section id="accounts" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    4. User Accounts and Responsibilities
                </h2>

                <ul class="list-disc pl-6 space-y-3 text-sm text-slate-600 leading-7">

                    <li>Users must provide truthful and accurate registration information.</li>

                    <li>Users are responsible for keeping their login credentials confidential.</li>

                    <li>Users must not share their accounts with unauthorized individuals.</li>

                    <li>Users must immediately report suspected unauthorized access to the barangay.</li>

                    <li>The barangay may review, suspend, or restrict accounts when there is a valid security or administrative reason, subject to applicable law.</li>

                </ul>

            </section>

            <!-- SERVICES -->
            <section id="services" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    5. Barangay Online Services
                </h2>

                <p class="text-sm text-slate-600 leading-7 mb-4">
                    The website may provide the following services,
                    depending on availability:
                </p>

                <ul class="list-disc pl-6 space-y-2 text-sm text-slate-600 leading-7">

                    <li>Resident account registration and profile management.</li>

                    <li>Online requests for barangay certificates and clearances.</li>

                    <li>Tracking of submitted document requests.</li>

                    <li>Viewing request status and administrative remarks.</li>

                    <li>Access to barangay announcements and information.</li>

                </ul>

                <p class="text-sm text-slate-600 leading-7 mt-4">
                    Online submission does not automatically guarantee
                    approval or issuance of a requested document.
                    Requests remain subject to verification,
                    applicable requirements, and barangay procedures.
                </p>

            </section>

            <!-- RESPONSIBILITIES -->
            <section id="responsibilities" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    6. User Responsibilities and Prohibited Activities
                </h2>

                <p class="text-sm text-slate-600 leading-7 mb-4">
                    Users agree not to:
                </p>

                <ul class="list-disc pl-6 space-y-2 text-sm text-slate-600 leading-7">

                    <li>Submit false, misleading, or fraudulent information.</li>

                    <li>Use another person's account without authorization.</li>

                    <li>Attempt to access restricted administrative pages.</li>

                    <li>Interfere with the operation or security of the website.</li>

                    <li>Upload malicious files or harmful content.</li>

                    <li>Use the system for unlawful or unauthorized purposes.</li>

                </ul>

            </section>

            <!-- PRIVACY -->
            <section id="privacy" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-2xl font-bold text-slate-900 mb-4">
                    7. Privacy Policy
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    Barangay Guindacpan respects the privacy of its
                    residents and users. Personal information collected
                    through the website shall be handled in accordance
                    with applicable Philippine laws, including
                    Republic Act No. 10173, also known as the
                    Data Privacy Act of 2012, and its implementing rules.
                </p>

                <p class="text-sm text-slate-600 leading-7 mt-3">
                    The barangay should process personal information
                    only for legitimate, declared, and lawful purposes
                    and apply appropriate safeguards.
                </p>

            </section>

            <!-- COLLECTION -->
            <section id="collection" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    8. Personal Information We May Collect
                </h2>

                <p class="text-sm text-slate-600 leading-7 mb-4">
                    Depending on the service requested, the website
                    may collect the following information:
                </p>

                <div class="grid sm:grid-cols-2 gap-4">

                    <div class="bg-slate-50 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 text-sm mb-2">
                            Personal Details
                        </h3>

                        <p class="text-sm text-slate-600 leading-6">
                            Full name, username, birthday, and residential address.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 text-sm mb-2">
                            Account Information
                        </h3>

                        <p class="text-sm text-slate-600 leading-6">
                            Login credentials and account-related records.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 text-sm mb-2">
                            Request Information
                        </h3>

                        <p class="text-sm text-slate-600 leading-6">
                            Document type, request purpose, tracking details, and status.
                        </p>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4">
                        <h3 class="font-semibold text-slate-900 text-sm mb-2">
                            Uploaded Information
                        </h3>

                        <p class="text-sm text-slate-600 leading-6">
                            Profile photographs and supporting documents, if required.
                        </p>
                    </div>

                </div>

                <p class="text-xs text-slate-500 mt-4 leading-6">
                    The actual information collected must be limited to
                    what is necessary for the specific service.
                </p>

            </section>

            <!-- USAGE -->
            <section id="usage" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    9. How We Use Personal Information
                </h2>

                <ul class="list-disc pl-6 space-y-2 text-sm text-slate-600 leading-7">

                    <li>To verify resident registration and account details.</li>

                    <li>To process barangay document requests.</li>

                    <li>To communicate updates regarding submitted requests.</li>

                    <li>To maintain accurate barangay administrative records.</li>

                    <li>To protect the security and integrity of the website.</li>

                    <li>To comply with applicable legal and government requirements.</li>

                </ul>

            </section>

            <!-- PROTECTION -->
            <section id="protection" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    10. Data Security and Protection
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The barangay should implement reasonable
                    organizational, physical, and technical security
                    measures to protect personal information against
                    unauthorized access, alteration, disclosure,
                    loss, or destruction.
                </p>

                <p class="text-sm text-slate-600 leading-7 mt-3">
                    Access to resident records should be limited to
                    authorized personnel whose official duties require
                    access to the information.
                </p>

            </section>

            <!-- SHARING -->
            <section id="sharing" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    11. Disclosure and Sharing of Information
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    Personal information shall not be publicly displayed
                    or disclosed to unauthorized individuals.
                    Information may be shared only when necessary
                    for a lawful barangay function, with proper
                    authorization, or when required or permitted by law.
                </p>

            </section>

            <!-- RIGHTS -->
            <section id="rights" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    12. Rights of Data Subjects
                </h2>

                <p class="text-sm text-slate-600 leading-7 mb-4">
                    Subject to applicable laws and limitations,
                    individuals may exercise their data privacy rights,
                    including:
                </p>

                <ul class="list-disc pl-6 space-y-2 text-sm text-slate-600 leading-7">

                    <li>The right to be informed about the processing of personal information.</li>

                    <li>The right to access personal information.</li>

                    <li>The right to correct inaccurate or incomplete information.</li>

                    <li>The right to object to certain types of processing.</li>

                    <li>The right to request erasure or blocking when legally applicable.</li>

                    <li>The right to lodge a complaint with the appropriate authority.</li>

                </ul>

                <p class="text-sm text-slate-600 leading-7 mt-4">
                    These rights may be subject to legal requirements,
                    legitimate government functions, and applicable
                    retention obligations.
                </p>

            </section>

            <!-- COOKIES -->
            <section id="cookies" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    13. Cookies, Sessions, and Website Security
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    The website may use session cookies and similar
                    technical mechanisms to maintain secure logins,
                    remember active sessions, and protect user accounts.
                    These mechanisms should only be used for legitimate
                    website functions and security purposes.
                </p>

            </section>

            <!-- CHANGES -->
            <section id="changes" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    14. Changes to These Policies
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    Barangay Guindacpan may update these Terms and
                    Conditions and Privacy Policy when necessary
                    to reflect changes in services, administrative
                    procedures, or applicable laws.
                    Updated policies shall be published on this page
                    together with their revision date.
                </p>

            </section>

            <!-- CONTACT -->
            <section id="contact" class="policy-section bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">

                <h2 class="text-xl font-bold text-slate-900 mb-4">
                    15. Contact Information and Privacy Concerns
                </h2>

                <p class="text-sm text-slate-600 leading-7">
                    For questions, requests, corrections, or concerns
                    regarding these policies or personal information,
                    please contact the Barangay Guindacpan Hall through
                    its official communication channels.
                </p>

                <div class="mt-5 bg-slate-50 rounded-xl p-5">

                    <h3 class="font-bold text-slate-900 mb-3">
                        Barangay Guindacpan
                    </h3>

                    <p class="text-sm text-slate-600">
                        Barangay Hall
                    </p>

                    <p class="text-sm text-slate-600 mt-1">
                        Guindacpan, Philippines
                    </p>

                    <p class="text-xs text-slate-500 mt-4">
                        Official contact details should be added here
                        after verification by the barangay.
                    </p>

                </div>

            </section>

            <!-- DISCLAIMER -->
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6">

                <h3 class="font-bold text-amber-900 mb-2">
                    Important Notice
                </h3>

                <p class="text-sm text-amber-900/80 leading-7">
                    This is a proposed website policy template.
                    Before official publication, Barangay Guindacpan
                    should review and approve its contents, identify
                    its Personal Information Controller and Data
                    Protection Officer or responsible privacy contact,
                    verify its actual data collection and retention
                    practices, and ensure compliance with applicable
                    Philippine data privacy requirements.
                </p>

            </div>

            <!-- BACK BUTTON -->
            <div class="flex flex-wrap gap-3 pt-3">

                <a href="main.php"
                   class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-semibold text-sm transition">
                    ← Back to Homepage
                </a>

                <button
                    onclick="window.print()"
                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold text-sm transition">
                    Print Policy
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
