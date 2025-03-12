
# Proyecto Sábanas  

Este es un sistema de gestión empresarial para la tienda de sábanas, que incluye los módulos de **Compras**, **Ventas** y **Tesorería**.  

## Módulos  

### Compras  
Gestión de compras y stock:  
- Registrar pedidos.  
- Registrar presupuesto de proveedor.  
- Generar órdenes de compras.  
- Gestionar compras y generar cuentas a pagar.  
- Registrar Libro de Compras.  
- Registrar ajustes de inventario.  
- Registrar Notas de Crédito y Débito.  
- Registrar Notas de Remisión.  
- Controlar el inventario por depósitos.  
- Elaborar informes web.  

### Ventas  
Gestión de ventas y cobranzas:  
- Registrar apertura y cierre de caja.  
- Generar el arqueo de caja.  
- Generar recaudaciones a depositar.  
- Registrar pedidos de clientes (opcional).  
- Gestionar ventas y generar cuentas a cobrar.  
- Registrar Libro de Ventas.  
- Gestionar cobranzas por forma de cobro (efectivo, cheque, tarjeta de crédito y débito) e imprimir recibos.  
- Registrar Notas de Remisión.  
- Gestionar Notas de Crédito y Débito.  
- Elaborar informes web.  

### Tesorería  
Gestión financiera y pagos:  
- Cargar facturas y generar provisión de cuentas a pagar y IVA de compras.  
- Generar órdenes de pago e imprimir cheques.  
- Registrar la entrega de cheques a proveedores.  
- Generar procesos especiales como anulación de órdenes de pago y cheques.  
- Asignar fondo fijo y rendirlo.  
- Reponer fondo fijo y generar cuentas a pagar.  
- Generar conciliación bancaria.  
- Cargar otros débitos y créditos.  
- Registrar depósitos (boletas de depósitos).  
- Generar liquidación de cobros con tarjetas de crédito.  

## Instalación  

### 1. Requisitos Previos  
- **XAMPP** o **Laragon** instalado.  
- **PHP 8.0**PHP 8.0.28  configurado en el servidor local.  
- **PostgreSQL** instalado y funcionando.  

### 2. Clonar el Repositorio  
```
git clone <URL_DEL_REPOSITORIO>
cd <NOMBRE_DEL_PROYECTO>
```  

### 3. Montar la Base de Datos en PostgreSQL  
- Ubica el archivo con la estructura de la base de datos (`base_de_datos.sql`).  
- Importa el archivo en PostgreSQL usando **pgAdmin** o la terminal:  
  ```
  psql -U usuario -d nombre_de_tu_base -f base_de_datos.sql
  ```  

### 4. Configurar la Conexión a la Base de Datos  
- Ubica el archivo de configuración (`.env`, `config/database.php`, u otro según el proyecto).  
- Modifica los valores de conexión con los datos de tu servidor PostgreSQL:  
  ```
  DB_HOST=localhost
  DB_PORT=5432
  DB_DATABASE=nombre_de_tu_base
  DB_USERNAME=usuario
  DB_PASSWORD=contraseña
  ```  

### 5. Configurar el Servidor Web  
- Si usas **XAMPP**, asegúrate de que **Apache** y **PostgreSQL** estén corriendo.  
- Si usas **Laragon**, abre **Menu > Apache > Versión PHP** y selecciona **PHP 8.0**.  
- Coloca el proyecto en la carpeta `htdocs` (XAMPP) o `www` (Laragon).  

### 6. Iniciar la Aplicación  

  ```
  http://localhost/NOMBRE_DEL_PROYECTO
  ```  
 
  ```

  ```  

## Uso  

Cada módulo tiene una interfaz web para gestionar sus funciones. Los usuarios pueden navegar por los diferentes módulos desde el panel de administración.  

## Contribución  

Si deseas contribuir al proyecto, por favor realiza un **fork** del repositorio y abre un **pull request** para agregar nuevas características o corregir errores.  

## Autor  

**Marco Alejandro Cáceres Irala**  
