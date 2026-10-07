import boloAvengers from "../img/bolo-avengers.jpg";
import boloPrincesa from "../img/bolo-da-princesa-sofia.jpg";
import boloSuper from "../img/bolo-super.jpg";

import "../style/Main.css"


export function Main() {
    return(
     <main>
        <div id="carouselExampleIndicators" class="carousel slide">
  <div class="carousel-indicators">
    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
  </div>
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src={boloPrincesa} class="d-block w-100" alt="boloSurprea"></img>
    </div>
    <div class="carousel-item">
      <img src={boloSuper} class="d-block w-100" alt="boloSuper"></img>
    </div>
    <div class="carousel-item">
      <img src={boloAvengers} class="d-block w-100" alt="boloAvenger"></img>
    </div>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>
 </main>
    )
}