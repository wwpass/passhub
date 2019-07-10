(function () {
'use strict';

const body = document.querySelector('.loginPage');
const getStarteds = body.querySelectorAll('.button--getStarted');
const instruction = body.querySelector('.landingContent--instruction');
const alreadyHaveBtn = instruction.querySelector('.button--icon');
const overlay = body.querySelector('.overlay');
const hintIconTop = body.querySelector('.landingContent__icon--hintTop');
const hintIconBottom = body.querySelector('.landingContent__icon--hintQr');
const hintInfoTop = body.querySelector('.landingContent__hintInfo--top');
const hintInfoBottom = body.querySelector('.landingContent__hintInfo--bottom');

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

getStarteds.forEach((item) => {
  item.addEventListener('click', getStartedOnClick);
});

/*  no hints
function hintTextClose() {
  overlay.classList.remove('overlay--visible');
  const popupHint = body.querySelector('.popupHint');
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

hintIconTop.addEventListener('click', () => {
  hintIconOnClick(hintInfoTop);
});

hintIconBottom.addEventListener('click', () => {
  hintIconOnClick(hintInfoBottom);
});
*/
}());

//# sourceMappingURL=landing.js.map
