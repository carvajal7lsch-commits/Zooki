-- Tablas y columnas afectadas, antes de 07; datos sintéticos con referencias históricas.
SET NAMES utf8mb4;
CREATE TABLE especies (id_especie INT PRIMARY KEY, nombre_especie VARCHAR(50) UNIQUE) ENGINE=InnoDB;
CREATE TABLE razas (id_raza INT PRIMARY KEY, id_especie INT NOT NULL, nombre_raza VARCHAR(50), UNIQUE KEY (id_especie, nombre_raza)) ENGINE=InnoDB;
INSERT INTO especies VALUES (1, 'Canino');
CREATE TABLE grafo_nodos (
  id_nodo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('sintoma','diagnostico','farmaco','especie','raza','condicion') NOT NULL,
  etiqueta VARCHAR(150) NOT NULL,
  KEY idx_grafo_nodos_tipo (tipo, etiqueta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE grafo_aristas (
  id_arista INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_origen INT NOT NULL,
  id_destino INT NOT NULL,
  tipo_relacion VARCHAR(50) NOT NULL,
  signo ENUM('positivo','negativo') NOT NULL,
  peso DECIMAL(4,3) DEFAULT 1.000,
  CONSTRAINT fk_arista_origen FOREIGN KEY (id_origen) REFERENCES grafo_nodos(id_nodo),
  CONSTRAINT fk_arista_destino FOREIGN KEY (id_destino) REFERENCES grafo_nodos(id_nodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO grafo_nodos VALUES (1, 'especie', 'Concepto anterior'), (2, 'sintoma', 'Síntoma anterior');
INSERT INTO grafo_aristas VALUES (1, 1, 2, 'asociado', 'positivo', 1);
CREATE TABLE citas (id_cita INT PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE cita_sintomas (id_cita INT, id_nodo INT, PRIMARY KEY(id_cita, id_nodo), FOREIGN KEY(id_nodo) REFERENCES grafo_nodos(id_nodo)) ENGINE=InnoDB;
CREATE TABLE consulta_sintomas (id_consulta INT, id_nodo INT, PRIMARY KEY(id_consulta, id_nodo), FOREIGN KEY(id_nodo) REFERENCES grafo_nodos(id_nodo)) ENGINE=InnoDB;
INSERT INTO consulta_sintomas VALUES (1, 2);
