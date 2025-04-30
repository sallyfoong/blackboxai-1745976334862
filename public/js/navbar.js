async function loadNavbar() {
  const response = await fetch('/components/navbar.html');
  const navbarHtml = await response.text();
  document.getElementById('navbar-placeholder').innerHTML = navbarHtml;
}

document.addEventListener('DOMContentLoaded', loadNavbar);
