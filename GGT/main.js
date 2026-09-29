import {Navbar} from './components/NavBar.js';
import { Router } from './router.js';

const navbar = document.querySelector('.navbar');
navbar.innerHTML = Navbar();


document.addEventListener('click', Event=>{
    const link = Event.target.closest('[data-link]');
    Event.preventDefault();
    const url = link.getAttribute('href');
    history.pushState(null,'',url);
    Router();
});
window.addEventListener('popstate',Router);