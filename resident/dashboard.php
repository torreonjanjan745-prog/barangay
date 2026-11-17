

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resident Dashboard | Barangay Guindacpan</title>

    <!-- TAILWIND CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 text-gray-800">

<div class="min-h-screen flex">

    <!-- =====================================
         SIDEBAR
    ====================================== -->

    <aside class="w-64 bg-slate-900 text-white hidden md:block">

        <!-- BRAND -->
        <div class="p-6 border-b border-slate-700">

            <h1 class="text-xl font-bold">
                Barangay Guindacpan
            </h1>

            <p class="text-sm text-gray-400 mt-1">
                Resident Portal
            </p>

        </div>

        <!-- NAVIGATION -->
        <nav class="p-4 space-y-2">

            <a href="#dashboard"
               class="block bg-blue-600 px-4 py-3 rounded-lg hover:bg-blue-700">
                Dashboard
            </a>

            <a href="#profile"
               class="block px-4 py-3 rounded-lg hover:bg-slate-800">
                My Profile
            </a>

            <a href="#services"
               class="block px-4 py-3 rounded-lg hover:bg-slate-800">
                Barangay Services
            </a>

            <a href="#requests"
               class="block px-4 py-3 rounded-lg hover:bg-slate-800">
                My Requests
            </a>

            <a href="#announcements"
               class="block px-4 py-3 rounded-lg hover:bg-slate-800">
                Announcements
            </a>

        </nav>

        <!-- LOGOUT -->
        <div class="p-4 mt-10">

            <a href="resident_logout.php"
               class="block text-center bg-red-600 hover:bg-red-700 px-4 py-3 rounded-lg">
                Logout
            </a>

        </div>

    </aside>


    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="flex-1 p-6 md:p-10" id="dashboard">

        <!-- HEADER -->
        <header class="flex flex-col sm:flex-row justify-between gap-4 mb-10">

            <div>

                <h2 class="text-3xl font-bold">
                    Resident Dashboard
                </h2>

                <p class="text-gray-500 mt-2">
                    Welcome back,
              
                </p>

            </div>

            <!-- PORTAL INFORMATION -->
            <div class="bg-white px-5 py-3 rounded-xl shadow-sm">

                <p class="text-sm text-gray-500">
                    Barangay Guindacpan
                </p>

                <p class="font-semibold">
                    Resident Portal
                </p>

            </div>

        </header>


        <!-- =====================================
             OVERVIEW CARDS
        ====================================== -->

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">

            <!-- TOTAL REQUESTS -->
            <div class="bg-white p-6 rounded-xl shadow-sm">

                <p class="text-gray-500">
                    Total Requests
                </p>

                <h3 class="text-3xl font-bold mt-3">0</h3>

            </div>

            <!-- PENDING REQUESTS -->
            <div class="bg-white p-6 rounded-xl shadow-sm">

                <p class="text-gray-500">
                    Pending Requests
                </p>

                <h3 class="text-3xl font-bold mt-3 text-yellow-600">0</h3>

            </div>

            <!-- APPROVED REQUESTS -->
            <div class="bg-white p-6 rounded-xl shadow-sm">

                <p class="text-gray-500">
                    Approved Requests
                </p>

                <h3 class="text-3xl font-bold mt-3 text-green-600">0</h3>

            </div>

        </section>


        <!-- =====================================
             BARANGAY SERVICES
        ====================================== -->

        <section id="services" class="mb-10">

            <h2 class="text-2xl font-bold mb-5">
                Barangay Services
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <!-- BARANGAY CLEARANCE -->
                <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition">

                    <h3 class="font-bold text-lg">
                        Barangay Clearance
                    </h3>

                    <p class="text-gray-500 text-sm mt-2">
                        Request a barangay clearance certificate.
                    </p>

                    <a href="request_clearance.php"
                       class="inline-block mt-5 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition">
                        Request Now
                    </a>

                </div>

                <!-- CERTIFICATE OF INDIGENCY -->
                <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition">

                    <h3 class="font-bold text-lg">
                        Certificate of Indigency
                    </h3>

                    <p class="text-gray-500 text-sm mt-2">
                        Request a certificate of indigency.
                    </p>

                    <a href="request_indigency.php"
                       class="inline-block mt-5 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition">
                        Request Now
                    </a>

                </div>

                <!-- CERTIFICATE OF RESIDENCY -->
                <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition">

                    <h3 class="font-bold text-lg">
                        Certificate of Residency
                    </h3>

                    <p class="text-gray-500 text-sm mt-2">
                        Request proof of barangay residency.
                    </p>

                    <a href="request_residency.php"
                       class="inline-block mt-5 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition">
                        Request Now
                    </a>

                </div>

            </div>

        </section>


        <!-- =====================================
             MY REQUESTS
        ====================================== -->

        <section id="requests"
                 class="bg-white p-6 rounded-xl shadow-sm mb-10">

            <h2 class="text-2xl font-bold mb-5">
                My Recent Requests
            </h2>

            <div class="overflow-x-auto">

                <table class="w-full text-left">

                    <thead class="border-b text-gray-500">

                        <tr>
                            <th class="py-3">Request</th>
                            <th class="py-3">Date</th>
                            <th class="py-3">Status</th>
                        </tr>

                    </thead>

                    <tbody>

                        <tr>
                            <td colspan="3"
                                class="text-center py-8 text-gray-400">
                                No requests found.
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =====================================
             BARANGAY ANNOUNCEMENTS
        ====================================== -->

        <section id="announcements"
                 class="bg-white p-6 rounded-xl shadow-sm mb-10">

            <h2 class="text-2xl font-bold mb-5">
                Barangay Announcements
            </h2>

            <p class="text-gray-500">
                No announcements available at the moment.
            </p>

        </section>


        <!-- =====================================
             RESIDENT PROFILE
        ====================================== -->

        <section id="profile"
                 class="bg-white p-6 rounded-xl shadow-sm">

            <h2 class="text-2xl font-bold mb-4">
                My Account
            </h2>

            <p class="text-gray-600">
                Resident Name:

               
                
            </p>

            <p class="text-sm text-gray-400 mt-3">
                Manage your personal information through your resident account.
            </p>

        </section>


        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="text-center text-gray-400 text-sm mt-10">

            © <?php echo date("Y"); ?>
            Barangay Guindacpan.

            All Rights Reserved.

        </footer>

    </main>

</div>

</body>
</html>
<?php


