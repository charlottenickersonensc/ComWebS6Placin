import { useState } from "react";
import './index.css'; 

function Home() {
  const [showLogin, setShowLogin] = useState(false);
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");

  function LoginForm() {
    return (
      <div className="form">
        <input
          type="text"
          placeholder="Identifiant"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
        />
        <input
          type="password"
          placeholder="Mot de passe"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
      </div>
    );
  }

  function handleLogin() {
    console.log("Connexion avec :", username, password);
  }

  function Navigation() {
    return (
      <div className="navigation">
        <button>Accueil</button>
        <button>Pages Élèves</button>
        <button>Pages Professeurs</button>
      </div>
    );
  }

  return (
    <div className="container">
      <h1>Bienvenue</h1>
      {!showLogin && (
        <button className="primary-button" onClick={() => setShowLogin(true)}>
          Se connecter
        </button>
      )}
      {showLogin && (
        <>
          <LoginForm />
          <button className="primary-button" onClick={handleLogin}>Entrer</button>
        </>
      )}
      {username && password && <Navigation />}
    </div>
  );
}

export default Home;
