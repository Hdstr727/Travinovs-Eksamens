<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner+</title>
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
            <h1 class="text-4xl font-bold">Planner+</h1>
            <nav class="mt-4">
                <ul class="flex justify-center gap-8">
                    <li><a href="#" class="text-lg font-semibold hover:underline">Home</a></li>
                    <li><a href="#features" class="text-lg font-semibold hover:underline">Features</a></li>
                    <li><a href="#testimonials" class="text-lg font-semibold hover:underline">Testimonials</a></li>
                    <li><a href="#contact" class="text-lg font-semibold hover:underline">Contact</a></li>
                    <li><a href="https://kristovskis.lv/3pt1/travinovs/Travinovs-Eksamens/authenticated-view/core/login.php" class="bg-white text-red-600 py-2 px-6 rounded-lg hover:bg-red-100">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-blue-50 text-center py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-semibold mb-4" style="color: #e63946;">Organize your work easily and efficiently!</h2>
            <p class="text-xl text-gray-600 mb-8">Planner+ helps you manage tasks, projects, and teamwork all in one place.</p>
            <a href="https://kristovskis.lv/3pt1/travinovs/Travinovs-Eksamens/authenticated-view/core/login.php" class="text-white py-3 px-8 rounded-lg text-xl font-semibold" style="background-color: #e63946;">Start Now</a>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16 bg-white">
        <div class="container mx-auto text-center px-4">
            <h2 class="text-3xl font-semibold mb-10" style="color: #e63946;">Features</h2>
            <div class="grid md:grid-cols-3 gap-12">
                <div class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-tasks text-4xl mb-4" style="color: #e63946;"></i>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Task Management</h3>
                    <p class="text-gray-600">Easily plan and organize your tasks.</p>
                </div>
                <div class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-users text-4xl mb-4" style="color: #e63946;"></i>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Team Collaboration</h3>
                    <p class="text-gray-600">Collaborate with your team in real-time.</p>
                </div>
                <div class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-chart-line text-4xl mb-4" style="color: #e63946;"></i>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Productivity Analysis</h3>
                    <p class="text-gray-600">Track your progress and efficiency.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="bg-blue-50 text-center py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-semibold mb-10" style="color: #e63946;">Testimonials</h2>
            <div class="flex justify-center gap-16">
                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <p class="text-gray-600 mb-4">"Planner+ has improved our team's workflow and productivity. Highly recommended!"</p>
                    <h4 class="font-semibold text-gray-800">John Smith</h4>
                    <p class="text-gray-600">Project Manager</p>
                </div>
                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <p class="text-gray-600 mb-4">"A simple but powerful tool that helps me stay on top of tasks and team updates."</p>
                    <h4 class="font-semibold text-gray-800">Jane Doe</h4>
                    <p class="text-gray-600">Marketing Specialist</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section
    <section id="contact" class="py-16 bg-white">
        <div class="container mx-auto text-center px-4">
            <h2 class="text-3xl font-semibold mb-10" style="color: #e63946;">Contact</h2>
            <form action="#" method="POST" class="max-w-lg mx-auto">
                <input type="text" name="name" placeholder="Your name" class="w-full mb-4 p-4 border border-gray-300 rounded-lg" required>
                <input type="email" name="email" placeholder="Your email" class="w-full mb-4 p-4 border border-gray-300 rounded-lg" required>
                <textarea name="message" placeholder="Your message" class="w-full mb-4 p-4 border border-gray-300 rounded-lg" required></textarea>
                <button type="submit" class="bg-red-600 text-white py-3 px-8 rounded-lg hover:bg-red-700">Send Message</button>
            </form>
        </div>
    </section> -->

    <!-- Footer -->
    <footer class="text-white text-center py-6 mt-16" style="background-color: #e63946;">
        <p>&copy; 2025 Planner+. All rights reserved.</p>
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
