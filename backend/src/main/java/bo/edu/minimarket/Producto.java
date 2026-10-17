package bo.edu.minimarket;

import java.math.BigDecimal;
import java.time.LocalDate;

/** JSON enviado y recibido por la API. El campo eliminado no se acepta desde el formulario. */
public class Producto {
    public Integer id;
    public String nombre;
    public String proveedor;
    public LocalDate fecha_vencimiento;
    public BigDecimal precio;
    public Integer stock;
}
