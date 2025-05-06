-- Create database
CREATE DATABASE IF NOT EXISTS school_db;
USE school_db;

-- Create Utilisateurs table
CREATE TABLE IF NOT EXISTS Utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    type_utilisateur ENUM('eleve', 'professeur', 'admin') NOT NULL
);

-- Create Matiere table
CREATE TABLE IF NOT EXISTS Matiere (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    description TEXT,
    id_professeur INT,
    FOREIGN KEY (id_professeur) REFERENCES Utilisateurs(id)
);

-- Create Classes/Groupes table
CREATE TABLE IF NOT EXISTS Classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL
);

-- Create Eleves_Classes junction table
CREATE TABLE IF NOT EXISTS Eleves_Classes (
    id_eleve INT,
    id_classe INT,
    PRIMARY KEY (id_eleve, id_classe),
    FOREIGN KEY (id_eleve) REFERENCES Utilisateurs(id),
    FOREIGN KEY (id_classe) REFERENCES Classes(id)
);

-- Create Notes table
CREATE TABLE IF NOT EXISTS Notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_eleve INT,
    id_matiere INT,
    valeur FLOAT NOT NULL,
    commentaire TEXT,
    date DATE NOT NULL,
    FOREIGN KEY (id_eleve) REFERENCES Utilisateurs(id),
    FOREIGN KEY (id_matiere) REFERENCES Matiere(id)
);

-- Insert some test data
INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe, type_utilisateur) VALUES
('Dupont', 'Jean', 'jean.dupont@example.com', '$2y$10$uG5AsM2VTgY2cw/X.L6XWehIg6nStZiDWxxOYvUGAYMc17gJ4Whb6', 'professeur'),
('Martin', 'Sophie', 'sophie.martin@example.com', '$2y$10$uG5AsM2VTgY2cw/X.L6XWehIg6nStZiDWxxOYvUGAYMc17gJ4Whb6', 'professeur'),
('Dubois', 'Pierre', 'pierre.dubois@example.com', '$2y$10$uG5AsM2VTgY2cw/X.L6XWehIg6nStZiDWxxOYvUGAYMc17gJ4Whb6', 'eleve'),
('Lefebvre', 'Marie', 'marie.lefebvre@example.com', '$2y$10$uG5AsM2VTgY2cw/X.L6XWehIg6nStZiDWxxOYvUGAYMc17gJ4Whb6', 'eleve'),
('Bernard', 'Lucas', 'lucas.bernard@example.com', '$2y$10$uG5AsM2VTgY2cw/X.L6XWehIg6nStZiDWxxOYvUGAYMc17gJ4Whb6', 'eleve');

INSERT INTO Classes (nom) VALUES
('Seconde A'),
('Seconde B'),
('Première S'),
('Terminale S');

INSERT INTO Matiere (nom, description, id_professeur) VALUES
('Mathématiques', 'Cours de mathématiques générales', 1),
('Français', 'Cours de littérature française', 2),
('Physique-Chimie', 'Cours de sciences physiques', 1);

INSERT INTO Eleves_Classes (id_eleve, id_classe) VALUES
(3, 3),
(4, 3),
(5, 4);

INSERT INTO Notes (id_eleve, id_matiere, valeur, commentaire, date) VALUES
(3, 1, 15.5, 'Bon travail', '2025-03-15'),
(3, 2, 13.0, 'Peut mieux faire', '2025-03-10'),
(4, 1, 17.0, 'Excellent', '2025-03-15'),
(5, 1, 12.0, 'Travail moyen', '2025-03-15'),
(5, 3, 14.5, 'Bonne participation', '2025-03-12');