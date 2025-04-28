<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plānotājs+</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://unpkg.com/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .carousel {
            position: relative;
            max-width: 100%;
            overflow: hidden;
        }
        .carousel-images {
            display: flex;
            transition: transform 0.5s ease;
        }
        .carousel-images img {
            width: 100%;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <!-- Header -->
    <header class="text-white py-6 shadow-md" style="background-color: #e63946;">
        <div class="container mx-auto text-center">
            <h1 class="text-4xl font-bold">Plānotājs+</h1>
            <nav class="mt-4">
                <ul class="flex justify-center gap-8">
                    <li><a href="#" class="text-lg font-semibold hover:underline">Sākums</a></li>
                    <li><a href="#features" class="text-lg font-semibold hover:underline">Funkcijas</a></li>
                    <li><a href="#testimonials" class="text-lg font-semibold hover:underline">Atsauksmes</a></li>
                    <li><a href="#contact" class="text-lg font-semibold hover:underline">Kontakti</a></li>
                    <li><a href="https://kristovskis.lv/3pt1/travinovs/Travinovs-Eksamens/authenticated-view/core/login.php" class="bg-white text-red-600 py-2 px-6 rounded-lg hover:bg-red-100">Pieteikties</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-blue-50 text-center py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-semibold mb-4" style="color: #e63946;">Organizējiet savu darbu vienkārši un efektīvi!</h2>
            <p class="text-xl text-gray-600 mb-8">Plānotājs+ palīdz jums pārvaldīt uzdevumus, projektus un komandas darbu vienuviet.</p>
            <a href="https://kristovskis.lv/3pt1/travinovs/Travinovs-Eksamens/authenticated-view/core/login.php" class="text-white py-3 px-8 rounded-lg text-xl font-semibold" style="background-color: #e63946; hover:bg-opacity-80;">Sākt Tagad</a>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16 bg-white">
        <div class="container mx-auto text-center px-4">
            <h2 class="text-3xl font-semibold mb-10" style="color: #e63946;">Funkcijas</h2>
            <div class="grid md:grid-cols-3 gap-12">
                <div class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-tasks text-4xl mb-4" style="color: #e63946;"></i>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Uzdevumu pārvaldība</h3>
                    <p class="text-gray-600">Viegli plānojiet un organizējiet savus uzdevumus.</p>
                </div>
                <div class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-users text-4xl mb-4" style="color: #e63946;"></i>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Komandas darbs</h3>
                    <p class="text-gray-600">Sadarbojieties ar savu komandu reāllaikā.</p>
                </div>
                <div class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-chart-line text-4xl mb-4" style="color: #e63946;"></i>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Produktivitātes analīze</h3>
                    <p class="text-gray-600">Sekojiet līdzi savam progresam un efektivitātei.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="bg-blue-50 text-center py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-semibold mb-10" style="color: #e63946;">Atsauksmes</h2>
            <div class="flex justify-center gap-16">
                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <p class="text-gray-600 mb-4">"Plānotājs+ ir uzlabojis mūsu komandas darbu un produktivitāti. Noteikti iesaku!"</p>
                    <h4 class="font-semibold text-gray-800">Jānis Bērziņš</h4>
                    <p class="text-gray-600">Projektu vadītājs</p>
                </div>
                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <p class="text-gray-600 mb-4">"Vienkāršs, bet efektīvs rīks, kas palīdz man sekot līdzi uzdevumiem un komandai."</p>
                    <h4 class="font-semibold text-gray-800">Inese Kalniņa</h4>
                    <p class="text-gray-600">Mārketinga speciāliste</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-white">
        <div class="container mx-auto text-center px-4">
            <h2 class="text-3xl font-semibold mb-10" style="color: #e63946;">Kontakti</h2>
            <form action="#" method="POST" class="max-w-lg mx-auto">
                <input type="text" name="name" placeholder="Jūsu vārds" class="w-full mb-4 p-4 border border-gray-300 rounded-lg" required>
                <input type="email" name="email" placeholder="Jūsu e-pasts" class="w-full mb-4 p-4 border border-gray-300 rounded-lg" required>
                <textarea name="message" placeholder="Jūsu ziņa" class="w-full mb-4 p-4 border border-gray-300 rounded-lg" required></textarea>
                <button type="submit" class="bg-red-600 text-white py-3 px-8 rounded-lg hover:bg-red-700">Sūtīt ziņu</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-white text-center py-6 mt-16" style="background-color: #e63946;">
        <p>&copy; 2025 Plānotājs+. Visas tiesības aizsargātas.</p>
    </footer>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-images img');
        const totalSlides = slides.length;

        function moveCarousel(direction) {
            currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
            document.querySelector('.carousel-images').style.transform = `translateX(-${currentSlide * 100}%)`;
        }
    </script>

</body>
</html>
