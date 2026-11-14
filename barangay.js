document.addEventListener('DOMContentLoaded', function () {
    const loginModal = document.getElementById('residentLogin');
    const closeLoginButton = document.getElementById('closeLogin');
    const loginLinks = document.querySelectorAll('[data-login-open]');
    const usernameInput = document.getElementById('residentUsername');
  
    function openLoginModal(event) {
      if (event) event.preventDefault();
      if (!loginModal) return;
      loginModal.classList.remove('hidden');
      loginModal.classList.add('flex');
      loginModal.style.display = 'flex';
      document.body.classList.add('overflow-hidden');
      if (usernameInput) usernameInput.focus();
    }
  
    function closeLoginModal() {
      if (!loginModal) return;
      loginModal.classList.add('hidden');
      loginModal.classList.remove('flex');
      loginModal.style.display = 'none';
      document.body.classList.remove('overflow-hidden');
    }
  
    loginLinks.forEach(function (link) {
      link.addEventListener('click', openLoginModal);
    });
    if (closeLoginButton) closeLoginButton.addEventListener('click', closeLoginModal);
    if (loginModal) {
      loginModal.addEventListener('click', function (event) {
        if (event.target === loginModal) closeLoginModal();
      });
    }
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && loginModal && !loginModal.classList.contains('hidden')) {
        closeLoginModal();
      }
    });
  
    // MOBILE NAVIGATION - MAO NI - GI-FIX - Kung walay menuBtn - Dili mo-error - Mao na
    const menuBtn = document.getElementById("menuBtn");
    const mobileMenu = document.getElementById("mobileMenu");
    if (menuBtn && mobileMenu) {
      menuBtn.addEventListener("click", function () {
        mobileMenu.classList.toggle("open");
        const isOpen = mobileMenu.classList.contains("open");
        menuBtn.setAttribute("aria-expanded", isOpen);
        menuBtn.innerHTML = isOpen ? "&times;" : "☰";
      });
      const mobileLinks = mobileMenu.querySelectorAll("a");
      mobileLinks.forEach(function (link) {
        link.addEventListener("click", function () {
          mobileMenu.classList.remove("open");
          menuBtn.setAttribute("aria-expanded", "false");
          menuBtn.innerHTML = "☰";
        });
      });
    }
  
    // HERO IMAGE SLIDESHOW
    const images = ["asset/bg.jpg", "asset/seasion.jpg", "asset/seasion1.jpg", "asset/work.jpg", "asset/gu.jpg"];
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
  });