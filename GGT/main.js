import {Navbar} from './components/NavBar.js';
import { Router } from './router.js';

const navbar = document.querySelector('#navbar');
navbar.innerHTML = Navbar();


document.addEventListener('click', event=>{
    const link = event.target.closest('[data-link]');
    event.preventDefault();
    const url = link.getAttribute('href');
    history.pushState(null,'',url);
    Router();
});
window.addEventListener('popstate',Router);