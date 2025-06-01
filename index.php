<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner+ - Streamline Your Workflow & Boost Productivity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://unpkg.com/tailwindcss@^2.0/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public-dark-theme.css">
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; }
        .text-brand { color: #e63946; }
        .bg-brand { background-color: #e63946; }
        .border-brand { border-color: #e63946; }
        .hover-bg-brand-dark:hover { background-color: #c32d39; }

        .feature-card, .testimonial-card, .faq-item {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }
        .section-title {
            font-size: 2.25rem; /* text-4xl */
            font-weight: 700; /* bold */
            margin-bottom: 1.5rem; /* mb-6 */
        }
        .section-subtitle {
            font-size: 1.25rem; /* text-xl */
            color: #4B5563; /* text-gray-600 */
            margin-bottom: 4rem; /* mb-16 */
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        #public-dark-mode-toggle {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        #public-dark-mode-toggle:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <!-- Header -->
    <header id="mainHeader" class="bg-brand text-white py-4 shadow-lg fixed w-full z-50 top-0 transition-all duration-300">
        <div class="container mx-auto flex justify-between items-center px-6">
            <a href="#" class="text-3xl font-extrabold tracking-tight">Planner<span class="text-red-300">+</span></a>
            <nav class="flex items-center">
                <ul class="hidden md:flex space-x-8 items-center">
                    <li><a href="#hero" class="text-lg font-medium hover:text-red-200 transition-colors">Home</a></li>
                    <li><a href="#why-us" class="text-lg font-medium hover:text-red-200 transition-colors">Why Us?</a></li>
                    <li><a href="#features" class="text-lg font-medium hover:text-red-200 transition-colors">Features</a></li>
                    <li><a href="#contact" class="text-lg font-medium hover:text-red-200 transition-colors">Contact</a></li>
                    <li><a href="authenticated-view/core/login.php" class="bg-white text-brand py-2 px-5 rounded-md font-semibold hover:bg-red-100 transition-colors login-button">Login</a></li>
                </ul>
                <button id="public-dark-mode-toggle" title="Toggle dark mode" class="ml-6 p-2 rounded-full transition-colors">
                    <i class="fas fa-moon text-xl"></i>
                </button>
                <button id="mobileMenuButton" class="md:hidden ml-4 text-white text-2xl p-2">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
        <div id="mobileMenu" class="md:hidden hidden bg-brand absolute w-full left-0 top-full shadow-xl rounded-b-lg">
            <ul class="flex flex-col items-center py-4 space-y-4">
                <li><a href="#hero" class="text-lg hover:text-red-200 transition-colors block py-2">Home</a></li>
                <li><a href="#why-us" class="text-lg hover:text-red-200 transition-colors block py-2">Why Us?</a></li>
                <li><a href="#features" class="text-lg hover:text-red-200 transition-colors block py-2">Features</a></li>
                <li><a href="#contact" class="text-lg hover:text-red-200 transition-colors block py-2">Contact</a></li>
                <li><a href="authenticated-view/core/login.php" class="bg-white text-brand py-2 px-6 rounded-md font-semibold hover:bg-red-100 transition-colors block w-3/4 text-center mx-auto login-button">Login</a></li>
            </ul>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="hero" class="bg-gradient-to-br from-red-50 to-blue-100 text-gray-800 text-center pt-40 pb-24 md:pt-48 md:pb-32">
        <div class="container mx-auto px-6">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-6 leading-tight">
                The <span class="text-brand">Smartest Way</span> to Manage Your Projects
            </h1>
            <p class="text-lg md:text-xl text-gray-600 mb-10 max-w-3xl mx-auto">
                Planner+ empowers teams to plan, track, and deliver projects of all sizes with intuitive tools, seamless collaboration, and powerful insights. Stop juggling, start achieving.
            </p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="authenticated-view/core/register.php" class="bg-brand text-white py-3 px-8 rounded-lg text-lg font-semibold hover-bg-brand-dark transition-transform transform hover:scale-105 shadow-md hover:shadow-lg w-full sm:w-auto">Get Started - It's Free!</a>
                <a href="#features" class="bg-white text-brand py-3 px-8 rounded-lg text-lg font-semibold border-2 border-brand hover:bg-red-50 transition-transform transform hover:scale-105 shadow-md hover:shadow-lg w-full sm:w-auto">Learn More <i class="fas fa-arrow-right ml-2"></i></a>
            </div>
            <p class="mt-6 text-sm text-gray-500">Planner+ is completely free to use. Start organizing today!</p>
        </div>
    </section>

    <!-- Why Choose Us / Problem-Solution Section -->
    <section id="why-us" class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center">
                <h2 class="section-title text-brand">Stop the Chaos. Start Collaborating.</h2>
                <p class="section-subtitle">Tired of scattered tasks, missed deadlines, and communication breakdowns? Planner+ brings clarity and efficiency to your workflow.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <img src="https://images.unsplash.com/photo-1517048676732-d65bc937f952?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Team Collaboration Visual" class="rounded-lg shadow-xl object-cover h-full w-full">
                </div>
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-12 w-12 rounded-full bg-brand text-white flex items-center justify-center text-xl shadow-md">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xl font-semibold text-gray-900">Centralized Workspace</h3>
                            <p class="text-gray-600 mt-1">Bring all your tasks, projects, files, and conversations into one organized hub. Say goodbye to scattered information.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-12 w-12 rounded-full bg-brand text-white flex items-center justify-center text-xl shadow-md">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xl font-semibold text-gray-900">Intuitive Planning</h3>
                            <p class="text-gray-600 mt-1">Visualize your workflow with flexible Kanban boards, lists, and calendars. Adapt Planner+ to your team's unique style.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-12 w-12 rounded-full bg-brand text-white flex items-center justify-center text-xl shadow-md">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xl font-semibold text-gray-900">Boosted Productivity</h3>
                            <p class="text-gray-600 mt-1">Automate repetitive tasks, get timely reminders, and gain insights into your performance to continuously improve.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="container mx-auto text-center px-6">
            <h2 class="section-title text-brand">Everything You Need, All in One Place</h2>
            <p class="section-subtitle">Planner+ is packed with features designed to make your work life simpler and more productive.</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-10">
                <div class="feature-card bg-white p-8 rounded-xl shadow-lg">
                    <div class="text-brand text-4xl mb-5"><i class="fas fa-sitemap"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-3">Kanban Boards</h3>
                    <p class="text-gray-600">Visualize your workflow and move tasks seamlessly through stages.</p>
                </div>
                <div class="feature-card bg-white p-8 rounded-xl shadow-lg">
                    <div class="text-brand text-4xl mb-5"><i class="fas fa-calendar-alt"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-3">Integrated Calendar View</h3>
                    <p class="text-gray-600">See all your tasks with due dates in a clear calendar format, helping you manage deadlines effectively across projects.</p>
                </div>
                <div class="feature-card bg-white p-8 rounded-xl shadow-lg">
                    <div class="text-brand text-4xl mb-5"><i class="fas fa-comments"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-3">Real-time Chat</h3>
                    <p class="text-gray-600">Communicate with your team directly within projects and tasks.</p>
                </div>
                <div class="feature-card bg-white p-8 rounded-xl shadow-lg">
                    <div class="text-brand text-4xl mb-5"><i class="fas fa-bell"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-3">Customizable Notifications</h3>
                    <p class="text-gray-600">Stay informed without the clutter. Choose exactly which project activities you want to be notified about.</p>
                </div>
                <div class="feature-card bg-white p-8 rounded-xl shadow-lg">
                    <div class="text-brand text-4xl mb-5"><i class="fas fa-sliders-h"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-3">Project Customization</h3>
                    <p class="text-gray-600">Adapt your projects to your needs. Configure board settings, manage collaborators, and tailor your workspace.</p>
                </div>
                <div class="feature-card bg-white p-8 rounded-xl shadow-lg">
                    <div class="text-brand text-4xl mb-5"><i class="fas fa-user-shield"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-3">Permissions</h3>
                    <p class="text-gray-600">Control access and collaboration with different permission levels for team members on your projects.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-20 bg-white">
        <div class="container mx-auto px-6 text-center">
            <h2 class="section-title text-brand">Get Started in 3 Simple Steps</h2>
            <div class="grid md:grid-cols-3 gap-10 mt-12 max-w-4xl mx-auto">
                <div class="flex flex-col items-center">
                    <div class="bg-brand text-white rounded-full h-16 w-16 flex items-center justify-center text-2xl font-bold mb-4 shadow-lg">1</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Sign Up Free</h3>
                    <p class="text-gray-600 text-center">Create your Planner+ account in seconds. It's completely free!</p>
                </div>
                <div class="flex flex-col items-center">
                    <div class="bg-brand text-white rounded-full h-16 w-16 flex items-center justify-center text-2xl font-bold mb-4 shadow-lg">2</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Create Your First Project</h3>
                    <p class="text-gray-600 text-center">Set up your board, define tasks, and invite your team members if you wish.</p>
                </div>
                <div class="flex flex-col items-center">
                    <div class="bg-brand text-white rounded-full h-16 w-16 flex items-center justify-center text-2xl font-bold mb-4 shadow-lg">3</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Start Achieving</h3>
                    <p class="text-gray-600 text-center">Collaborate, track progress, and hit your goals with ease.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <h2 class="section-title text-brand text-center">What Our Users Say</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-10">
                <div class="testimonial-card bg-white p-8 rounded-xl shadow-lg">
                    <img src="https://ui-avatars.com/api/?name=Sarah+L&background=random&color=fff&size=64" alt="Sarah L" class="w-16 h-16 rounded-full mx-auto mb-5 border-2 border-brand">
                    <p class="text-gray-600 italic text-center mb-5">"Planner+ has been a game-changer for our remote team. Keeping track of tasks and deadlines is so much simpler now."</p>
                    <h4 class="font-semibold text-gray-800 text-center">Sarah L.</h4>
                    <p class="text-gray-500 text-sm text-center">Marketing Manager</p>
                </div>
                <div class="testimonial-card bg-white p-8 rounded-xl shadow-lg">
                    <img src="https://ui-avatars.com/api/?name=Mike+P&background=random&color=fff&size=64" alt="Mike P" class="w-16 h-16 rounded-full mx-auto mb-5 border-2 border-brand">
                    <p class="text-gray-600 italic text-center mb-5">"The visual boards are fantastic! We can see our entire project pipeline at a glance. Highly recommend."</p>
                    <h4 class="font-semibold text-gray-800 text-center">Mike P.</h4>
                    <p class="text-gray-500 text-sm text-center">Startup Founder</p>
                </div>
                <div class="testimonial-card bg-white p-8 rounded-xl shadow-lg">
                    <img src="https://ui-avatars.com/api/?name=Linda+K&background=random&color=fff&size=64" alt="Linda K" class="w-16 h-16 rounded-full mx-auto mb-5 border-2 border-brand">
                    <p class="text-gray-600 italic text-center mb-5">"We switched from a more complex tool, and Planner+ was a breath of fresh air. Easy to learn, powerful to use."</p>
                    <h4 class="font-semibold text-gray-800 text-center">Linda K.</h4>
                    <p class="text-gray-500 text-sm text-center">Operations Lead</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-white">
        <div class="container mx-auto px-6 max-w-3xl">
            <h2 class="section-title text-brand text-center">Frequently Asked Questions</h2>
            <div class="space-y-6">
                <div class="faq-item bg-gray-50 p-6 rounded-lg shadow-md">
                    <button class="flex justify-between items-center w-full text-left">
                        <h3 class="text-lg font-semibold text-gray-800">Is Planner+ really free?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform"></i>
                    </button>
                    <div class="mt-3 text-gray-600 max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
                        Yes! Planner+ offers a robust free version perfect for individuals and small teams. It includes all core features for task and project management.
                    </div>
                </div>
                <div class="faq-item bg-gray-50 p-6 rounded-lg shadow-md">
                    <button class="flex justify-between items-center w-full text-left">
                        <h3 class="text-lg font-semibold text-gray-800">How many projects and tasks can I create?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform"></i>
                    </button>
                    <div class="mt-3 text-gray-600 max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
                        In the free version of Planner+, you can create an unlimited number of projects and tasks. We believe in providing full functionality to help you get organized.
                    </div>
                </div>
                <div class="faq-item bg-gray-50 p-6 rounded-lg shadow-md">
                    <button class="flex justify-between items-center w-full text-left">
                        <h3 class="text-lg font-semibold text-gray-800">Can I collaborate with my team?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform"></i>
                    </button>
                    <div class="mt-3 text-gray-600 max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
                        Absolutely! Planner+ is designed for teamwork. You can invite team members to your boards, assign tasks, share files, and communicate in real-time.
                    </div>
                </div>
                 <div class="faq-item bg-gray-50 p-6 rounded-lg shadow-md">
                    <button class="flex justify-between items-center w-full text-left">
                        <h3 class="text-lg font-semibold text-gray-800">How secure is my data?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform"></i>
                    </button>
                    <div class="mt-3 text-gray-600 max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
                        We take your data security seriously. Your passwords are encrypted, and we employ robust security measures to protect your information and project data.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action / Contact Section -->
    <section id="contact" class="py-20 bg-brand text-white text-center">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to Get Organized?</h2>
            <p class="text-xl text-red-100 mb-10 max-w-2xl mx-auto">Join Planner+ today and take control of your tasks and projects. It's free and easy to get started!</p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-6">
                <a href="authenticated-view/core/register.php" class="bg-white text-brand py-3 px-8 rounded-lg text-lg font-semibold hover:bg-red-100 transition-transform transform hover:scale-105 shadow-md hover:shadow-lg w-full sm:w-auto">Sign Up - It's Free!</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 text-center py-16">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-3 gap-8 mb-10 text-left md:text-center">
                <div>
                    <h4 class="text-xl font-semibold text-white mb-4">Planner+</h4>
                    <p class="text-gray-400">The smart way to manage projects and boost team productivity. Plan, track, and achieve your goals.</p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#features" class="hover:text-brand transition-colors">Features</a></li>
                        <li><a href="#faq" class="hover:text-brand transition-colors">FAQ</a></li>
                        <li><a href="authenticated-view/core/register.php" class="hover:text-brand transition-colors">Sign Up</a></li>
                        <li><a href="authenticated-view/core/login.php" class="hover:text-brand transition-colors">Login</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Enjoy Planner+</h4>
                    <p class="text-gray-400">We hope Planner+ helps you organize your work and achieve your goals efficiently. Happy planning!</p>
                </div>
            </div>
            <hr class="border-gray-700 my-8">
            <p class="text-sm text-gray-500">© <?php echo date("Y"); ?> Planner+. All rights reserved.</p>
        </div>
    </footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const darkModeTogglePublic = document.getElementById('public-dark-mode-toggle');
    const htmlElementPublic = document.documentElement;
    const moonIcon = '<i class="fas fa-moon text-xl"></i>';
    const sunIcon = '<i class="fas fa-sun text-xl"></i>';

    function setPublicDarkMode(isDark) {
        if (isDark) {
            htmlElementPublic.classList.add('dark-mode');
            if (darkModeTogglePublic) darkModeTogglePublic.innerHTML = sunIcon;
        } else {
            htmlElementPublic.classList.remove('dark-mode');
            if (darkModeTogglePublic) darkModeTogglePublic.innerHTML = moonIcon;
        }
    }

    if (localStorage.getItem('darkMode') === 'true') {
        setPublicDarkMode(true);
    } else {
        setPublicDarkMode(false);
    }

    if (darkModeTogglePublic) {
        darkModeTogglePublic.addEventListener('click', () => {
            const isCurrentlyDark = htmlElementPublic.classList.contains('dark-mode');
            setPublicDarkMode(!isCurrentlyDark);
            localStorage.setItem('darkMode', !isCurrentlyDark);
        });
    }

    // Mobile Menu
    const mobileMenuButton = document.getElementById('mobileMenuButton');
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
            });
        });
    }

    // Sticky header
    const header = document.getElementById('mainHeader');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                header.classList.add('py-3', 'bg-opacity-95', 'backdrop-blur-sm');
                header.classList.remove('py-4');
            } else {
                header.classList.remove('py-3', 'bg-opacity-95', 'backdrop-blur-sm');
                header.classList.add('py-4');
            }
        });
    }
    
    // FAQ Accordion
    const faqItems = document.querySelectorAll('.faq-item button');
    faqItems.forEach(button => {
        button.addEventListener('click', () => {
            const content = button.nextElementSibling;
            const icon = button.querySelector('i');

            if (content.style.maxHeight && content.style.maxHeight !== "0px") {
                content.style.maxHeight = "0px";
                icon.classList.remove('rotate-180');
            } else {
                faqItems.forEach(otherButton => {
                    if (otherButton !== button) {
                        otherButton.nextElementSibling.style.maxHeight = "0px";
                        otherButton.querySelector('i').classList.remove('rotate-180');
                    }
                });
                content.style.maxHeight = content.scrollHeight + "px";
                icon.classList.add('rotate-180');
            }
        });
    });
});
</script>
</body>
</html>