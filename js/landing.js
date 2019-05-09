(function () {
'use strict';

const body = document.querySelector('.loginPage');
const getStarteds = document.querySelectorAll('.button--getStarted');
const instruction = document.querySelector('.landingContent--instruction');
const alreadyHaveBtn = instruction.querySelector('.button--icon');
const overlay = document.querySelector('.overlay');
const learnMoreBtn = document.querySelector('.button--more');
const learnMore = document.querySelector('.landingContent__info--more');
const hintIconTop = document.querySelector('.landingContent__icon--hintTop');
const hintIconBottom = document.querySelector('.landingContent__icon--hintQr');
const hintInfoTop = document.querySelector('.landingContent__hintInfo--top');
const hintInfoBottom = document.querySelector('.landingContent__hintInfo--bottom');
let videoFrame;
const videoUrl = 'https://www.youtube-nocookie.com/embed/FGlnn_noPwQ?rel=0&amp;showinfo=0';

function instructionCloseOnClick() {
  instruction.style.display = 'none';
  body.style.overflow = 'auto';
  overlay.classList.remove('overlay--visible');
  overlay.removeEventListener('click', instructionCloseOnClick);
  alreadyHaveBtn.removeEventListener('click', instructionCloseOnClick);
}

function getStartedOnClick() {
  const instructionClose = instruction.querySelector('.landingContent__icon--close');
  instruction.style.display = 'block';
  instruction.style.overflow = 'auto';
  body.style.overflow = 'hidden';
  overlay.classList.add('overlay--visible');
  instructionClose.addEventListener('click', instructionCloseOnClick);
  overlay.addEventListener('click', instructionCloseOnClick);
  alreadyHaveBtn.addEventListener('click', instructionCloseOnClick);
}

function learnMoreBtnOnClick() {
  learnMore.classList.toggle('displayNone');
  learnMoreBtn.remove();
}

function hintTextClose() {
  overlay.classList.remove('overlay--visible');
  const popupHint = document.querySelector('.popupHint');
  if (popupHint) {
    popupHint.classList.remove('displayBlock');
    popupHint.classList.remove('popupHint');
  }
  overlay.removeEventListener('click', hintTextClose);
}

function hintIconOnClick(element) {
  element.classList.add('displayBlock');
  element.classList.add('popupHint');
  overlay.classList.add('overlay--visible');
  overlay.addEventListener('click', hintTextClose);
}

function createVideoElement(url) {
  const similarVideoTemplate = document.querySelector('template').content;
  const videoElement = similarVideoTemplate.cloneNode(true);
  const videoFile = videoElement.querySelector('.promo__video');
  videoFile.src = url;
  return videoElement;
}

function fillFragment(url) {
  const fragment = document.createDocumentFragment();
  const promoBlock = document.querySelector('.loginMain');
  fragment.appendChild(createVideoElement(url));
  promoBlock.appendChild(fragment);
}

function deleteVideoElement() {
  const videoContainer = document.querySelector('.promo');
  videoContainer.remove();
}

function closeVideo() {
  videoFrame.style.display = 'none';
  deleteVideoElement();
  videoFrame.removeEventListener('click', closeVideo);
  overlay.classList.remove('overlay--visible');
  overlay.removeEventListener('click', closeVideo);
  body.style.overflow = 'auto';
}

function openVideo() {
  fillFragment(videoUrl);
  videoFrame = document.querySelector('.promo__video-container:not(.promo__video)');
  body.style.overflow = 'hidden';
  videoFrame.style.display = 'block';
  videoFrame.addEventListener('click', closeVideo);
  overlay.classList.add('overlay--visible');
  overlay.addEventListener('click', closeVideo);
}

function initVideo() {
  const videoButton = document.querySelector('.landingContent__icon--play');

  if (videoButton) {
    videoButton.addEventListener('click', openVideo);
  }
}

getStarteds.forEach((item) => {
  item.addEventListener('click', getStartedOnClick);
});
/*
hintIconTop.addEventListener('click', () => {
  hintIconOnClick(hintInfoTop);
});

hintIconBottom.addEventListener('click', () => {
  hintIconOnClick(hintInfoBottom);
});
*/
learnMoreBtn.addEventListener('click', learnMoreBtnOnClick);

window.initVideo = initVideo;

}());

//# sourceMappingURL=landing.js.map
