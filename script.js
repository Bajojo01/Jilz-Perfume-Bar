/* HERO CAROUSEL
   ------------------------------------------------------------ */
function resizeCarousel() {
  var container = document.querySelector('.heroSection .imgContainer');
  var wrapper = document.querySelector('.heroSection .hero .wrapper');
  var images = document.querySelectorAll('.heroSection .hero .wrapper img');

  if (!container || !wrapper || !images.length) return;

  var w = container.offsetWidth;
  var h = container.offsetHeight;

  wrapper.style.width = (w * images.length) + 'px';

  images.forEach(function (img) {
    img.style.width = w + 'px';
    img.style.height = h + 'px';
  });
}

window.addEventListener('load', resizeCarousel);
window.addEventListener('resize', resizeCarousel);


/* PERFUME CATEGORY FILTER
   ------------------------------------------------------------ */
// var perfCat = document.getElementById('perfCat');
// if (perfCat) {
//   perfCat.addEventListener('change', function () {
//     fetch('filter.php', {
//       method: 'POST',
//       headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
//       body: 'category=' + this.value
//     })
//       .then(function (res) { return res.text(); })
//       .then(function (data) {
//         document.getElementById('perfumeContainer').innerHTML = data;
//       });
//   });
// }


/* PACKAGE IMAGE PREVIEW
   ------------------------------------------------------------ */
var pkgImg1 = document.getElementById('Package_Img1');
if (pkgImg1) {
  pkgImg1.addEventListener('change', function (e) {
    var preview = document.getElementById('imgPreview1');
    preview.src = URL.createObjectURL(e.target.files[0]);
    preview.style.display = 'block';
  });
}


/* ADD PACKAGE POPUP FORM
   ------------------------------------------------------------ */
var addBtn = document.getElementById('addBtn');
var formPopup = document.getElementById('popupForm');
var closeForm = document.getElementById('closeForm');

if (addBtn && formPopup && closeForm) {
  addBtn.addEventListener('click', function () {
    formPopup.style.display = 'flex';
  });

  closeForm.addEventListener('click', function () {
    formPopup.style.display = 'none';
  });
}


/* MOBILE CAROUSEL TOUCH FIX
   ------------------------------------------------------------ */
function fixCarouselTouch() {
  var carousels = document.querySelectorAll(
    '.pckagesShortcut, .pfumesShortcut, .bookingContainer .infos .pics'
  );
  carousels.forEach(function (el) {
    var startX = 0;
    el.addEventListener('touchstart', function (e) {
      startX = e.touches[0].clientX;
    }, { passive: true });
    el.addEventListener('touchmove', function (e) {
      var dx = Math.abs(e.touches[0].clientX - startX);
      if (dx > 10) e.stopPropagation();
    }, { passive: true });
  });
}


/* MOBILE CAROUSEL DOTS
   ------------------------------------------------------------ */
function initCarouselDots(carousel, dotsContainer) {
  if (!carousel || !dotsContainer) return;

  var cards = carousel.querySelectorAll('.pckages, .pfumes');
  if (cards.length === 0) return;

  cards.forEach(function (_, i) {
    var dot = document.createElement('span');
    dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
    dotsContainer.appendChild(dot);
  });

  var dots = dotsContainer.querySelectorAll('.carousel-dot');

  carousel.addEventListener('scroll', function () {
    var cardWidth = cards[0].offsetWidth + 12;
    var idx = Math.round(carousel.scrollLeft / cardWidth);
    dots.forEach(function (d, i) {
      d.classList.toggle('active', i === idx);
    });
  }, { passive: true });
}

function initDots() {
  if (window.innerWidth > 480) return;

  var pkgCarousel = document.querySelector('.pckagesShortcut');
  if (pkgCarousel) {
    var pkgDots = pkgCarousel.nextElementSibling;
    if (!pkgDots || !pkgDots.classList.contains('carousel-dots')) {
      pkgDots = document.createElement('div');
      pkgDots.className = 'carousel-dots';
      pkgCarousel.after(pkgDots);
    }
    initCarouselDots(pkgCarousel, pkgDots);
  }

  var perfCarousel = document.querySelector('.pfumesShortcut');
  if (perfCarousel) {
    var perfDots = perfCarousel.nextElementSibling;
    if (!perfDots || !perfDots.classList.contains('carousel-dots')) {
      perfDots = document.createElement('div');
      perfDots.className = 'carousel-dots';
      perfCarousel.after(perfDots);
    }
    initCarouselDots(perfCarousel, perfDots);
  }
}


/* BOOT
   ------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
  fixCarouselTouch();
  initDots();
});