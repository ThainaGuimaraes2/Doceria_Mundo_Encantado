import { Routes,Route,BrowserRouter }  from 'react-router-dom'
import { Home } from './pages/Home'
import { Cardapio } from './pages/Cardapio'
import "../src/style/App.css"

function App() {
  return (
     <BrowserRouter>
     <Routes>
       <Route path='/' element={<Home />}/>
       <Route path='/Cardapio' element={<Cardapio />} />
     </Routes>
     </BrowserRouter>
  )
}

export default App
