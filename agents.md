# AGENTS.md

# Dironlex

## Contexto del proyecto

Dironlex es una aplicación Laravel 13 para la gestión documental y operativa de operadores de drones.

La aplicación está dividida en dos áreas claramente diferenciadas:

### Portal Cliente

Desarrollado con Laravel, Blade, Livewire y Flux.

Incluye:

* Dashboard
* Mi ficha
* Drones
* Pilotos
* Operaciones
* Operadora

El portal cliente se considera funcional y visualmente terminado.

### Gestor Interno

Desarrollado con Filament.

Accesible desde:

/admin

Es la zona principal de desarrollo y mejora continua.

---

# Prioridad actual del proyecto

La prioridad de desarrollo actual es el gestor interno basado en Filament.

Todas las nuevas funcionalidades, automatizaciones y mejoras deben centrarse principalmente en:

* Gestión de clientes.
* Gestión de operaciones.
* Gestión de expedientes de operadora.
* Gestión de requisitos.
* Gestión documental.
* Gestión de trámites.
* Automatizaciones internas.
* Productividad de gestores.
* Experiencia de uso del panel Filament.

## Portal cliente

El portal cliente debe considerarse estable.

No modificar por defecto:

* resources/views
* resources/css/app.css
* Componentes Livewire del cliente
* Responsive móvil
* Responsive escritorio
* Diseño visual

Solo intervenir en esta zona cuando la tarea lo solicite explícitamente.

---

# Roles

## Cliente

Accede al portal cliente.

Gestiona:

* Su ficha
* Drones
* Pilotos
* Operaciones
* Operadora

## Gestor

Accede al panel Filament.

Gestiona:

* Clientes
* Operaciones
* Expedientes
* Requisitos
* Trámites
* Validaciones
* Documentación

---

# Flujo de negocio principal

## Alta de cliente

Cuando un gestor crea un cliente:

1. Se crea el usuario de acceso.
2. Se crea el registro de cliente.
3. Se crea el expediente base de operadora.

## Flujo cliente

1. Completa ficha.
2. Registra al menos un dron.
3. Se habilitan pilotos.
4. Se habilitan operaciones.
5. Se habilita operadora.

## Operadora

Los requisitos pueden estar en:

* Pendiente
* En revisión
* Aprobado
* Corrección requerida

## Operaciones

Las operaciones tienen estados propios y pueden tener trámites asociados.

---

# Estructura Filament conocida

## Recursos

### ClienteResource

Gestión principal de clientes.

### OperacionResource

Gestión global de operaciones.

## Relation Managers

### DronesRelationManager

Gestión de drones del cliente.

### PilotosRelationManager

Gestión de pilotos del cliente.

### OperacionesRelationManager

Gestión de operaciones del cliente.

### OperadoraRequirementsRelationManager

Gestión de requisitos del expediente de operadora.

### OperacionTramitesRelationManager

Gestión de trámites asociados a operaciones.

## Widgets

### AesaRegistrationRequestsWidget

Solicitudes AESA.

### UpcomingOperacionesTableWidget

Próximas operaciones.

---

# Forma de trabajar

Antes de modificar código:

1. Analizar el problema completo.
2. Identificar los archivos afectados.
3. Revisar modelos relacionados.
4. Revisar recursos Filament relacionados.
5. Revisar Relation Managers relacionados.
6. Revisar migraciones afectadas.
7. Explicar brevemente el plan antes de cambios importantes.

## Prioridad de análisis

Cuando una petición pueda resolverse desde varias zonas del proyecto:

1. Revisar primero Filament.
2. Buscar reutilizar lógica existente.
3. Evitar duplicar funcionalidades.
4. Mantener la coherencia del gestor.

---

# Restricciones

No realizar sin una necesidad clara:

* Refactorizaciones masivas.
* Cambios de arquitectura.
* Reestructuración de carpetas.
* Cambios de autenticación.
* Cambios de roles.
* Cambios de permisos.
* Cambios de relaciones entre modelos.
* Cambios de estados de negocio.
* Instalación de paquetes externos.
* Cambios en migraciones existentes.
* Modificaciones del frontend cliente.

---

# Base de datos

Antes de crear nuevas tablas o columnas:

1. Revisar migraciones existentes.
2. Verificar que la funcionalidad no exista ya.
3. Explicar por qué es necesario el cambio.
4. Mantener consistencia con la estructura actual.

---

# Calidad del código

Al implementar cambios:

* Mantener el estilo existente.
* Evitar código duplicado.
* Reutilizar servicios y lógica existente.
* Mantener nombres consistentes.
* Minimizar impacto en otras áreas.
* No modificar archivos no relacionados.

---

# Validación

Si se modifica lógica PHP importante:

```bash
php artisan test
```

Si se modifica frontend:

```bash
npm run build
```

Comandos útiles:

```bash
php artisan route:list
php artisan test
npm run dev
npm run build
```

---

# Objetivo

Actuar como desarrollador senior especializado en Laravel 13, Livewire y Filament.

La prioridad principal es evolucionar y mejorar el gestor interno de Dironlex manteniendo estable el portal cliente y respetando la arquitectura existente del proyecto.
