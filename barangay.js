

// MOBILE NAVIGATION

const menuBtn = document.getElementById("menuBtn");
const mobileMenu = document.getElementById("mobileMenu");

menuBtn.addEventListener("click", function () {

   mobileMenu.classList.toggle("open");

   const isOpen = mobileMenu.classList.contains("open");

   menuBtn.setAttribute("aria-expanded", isOpen);

   menuBtn.innerHTML = isOpen ? "&times;" : "☰";

});


// CLOSE MOBILE MENU AFTER CLICKING A LINK

const mobileLinks = mobileMenu.querySelectorAll("a");

mobileLinks.forEach(function (link) {

   link.addEventListener("click", function () {

       mobileMenu.classList.remove("open");

       menuBtn.setAttribute("aria-expanded", "false");

       menuBtn.innerHTML = "☰";

   });

});


// HERO IMAGE SLIDESHOW

const images = [
   "asset/bg.jpg",
   "asset/metting.jpg",
   "asset/guindacpan.jpg",
   "asset/gu.jpg"
];

let index = 0;

const slide = document.getElementById("slide");

if (slide && images.length > 1) {

   setInterval(function () {

       slide.classList.add("fade-out");

       setTimeout(function () {

           index = (index + 1) % images.length;

           slide.src = images[index];

           slide.classList.remove("fade-out");

       }, 1000);

   }, 8000);

}