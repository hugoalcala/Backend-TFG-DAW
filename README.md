Backend-TFG-DAW
Este repositorio contiene el backend de la plataforma educativa desarrollada como Proyecto Final de Ciclo (DAW). El backend es el encargado de gestionar toda la lógica de negocio, la persistencia de datos y la comunicación con el frontend mediante una API REST.

La aplicación permite gestionar usuarios con distintos roles (alumno, profesor y administrador), publicaciones educativas, comentarios, mensajería interna y perfiles de profesores particulares. El backend actúa como núcleo central del sistema, garantizando la seguridad, la integridad de los datos y el correcto funcionamiento de la aplicación.

El proyecto está desarrollado siguiendo una arquitectura desacoplada, de manera que el backend puede funcionar de forma independiente al frontend, facilitando la escalabilidad, el mantenimiento y la reutilización de la API por otras posibles aplicaciones.

Entre sus principales responsabilidades se encuentran la autenticación y autorización de usuarios, la gestión de contenidos educativos, el control de permisos según el rol del usuario y la exposición de endpoints REST que consumirá el frontend.

Funcionalidades principales

Gestión de usuarios y autenticación

Sistema de roles y permisos

Publicación de contenidos educativos

Comentarios en publicaciones

Mensajería interna entre usuarios

Gestión de perfiles de profesores particulares

Panel de administración

API REST para comunicación con el frontend

Arquitectura

El backend sigue un patrón de arquitectura basado en API REST, separando claramente la lógica de negocio, el acceso a datos y la capa de presentación (API). La base de datos se gestiona mediante migraciones, lo que permite mantener un control de versiones del esquema y facilitar la evolución del proyecto.

Relación con el frontend

Este backend se comunica con el frontend a través de peticiones HTTP utilizando JSON como formato de intercambio de datos. El frontend consume los distintos endpoints expuestos para mostrar la información al usuario y permitir la interacción con el sistema.