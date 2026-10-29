let images = ["image1.jpg", "image2.jpg", "image3.jpg"];
let currentIndex = 0;
let sliderImage = document.getElementById("sliderImage");
let next=document.getElementById("next");
let previous=document.getElementById("previous");

next.addEventListener("click", function() {
    currentIndex++;
    if (currentIndex >= images.length) {
        currentIndex = 0;
    }
    slider.Image.src = images[currentIndex];
});
previous.addEventListener("click", function() {
    currentIndex--;
    if (currentIndex < 0) {
        currentIndex = images.length - 1;
    }
    slider.Image.src = images[currentIndex];
});