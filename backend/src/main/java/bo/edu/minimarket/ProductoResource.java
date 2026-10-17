package bo.edu.minimarket;

import jakarta.inject.Inject;
import jakarta.ws.rs.Consumes;
import jakarta.ws.rs.DELETE;
import jakarta.ws.rs.GET;
import jakarta.ws.rs.POST;
import jakarta.ws.rs.PUT;
import jakarta.ws.rs.Path;
import jakarta.ws.rs.PathParam;
import jakarta.ws.rs.Produces;
import jakarta.ws.rs.core.MediaType;
import jakarta.ws.rs.core.Response;

import javax.sql.DataSource;
import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.Date;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

@Path("/api/productos")
@Produces(MediaType.APPLICATION_JSON)
@Consumes(MediaType.APPLICATION_JSON)
public class ProductoResource {
    @Inject
    DataSource dataSource;

    private static Producto leer(ResultSet rs) throws SQLException {
        Producto p = new Producto();
        p.id = rs.getInt("id");
        p.nombre = rs.getString("nombre");
        p.proveedor = rs.getString("proveedor");
        Date fecha = rs.getDate("fecha_vencimiento");
        p.fecha_vencimiento = fecha == null ? null : fecha.toLocalDate();
        p.precio = rs.getBigDecimal("precio");
        p.stock = rs.getInt("stock");
        return p;
    }

    private static Response error(int status, String mensaje) {
        return Response.status(status).entity(Map.of("error", mensaje)).build();
    }

    private static String validar(Producto p) {
        if (p == null) return "Se requiere un producto JSON.";
        if (p.nombre == null || p.nombre.isBlank() || p.nombre.length() > 100)
            return "Nombre obligatorio (máximo 100 caracteres).";
        if (p.proveedor == null || p.proveedor.isBlank() || p.proveedor.length() > 100)
            return "Proveedor obligatorio (máximo 100 caracteres).";
        if (p.fecha_vencimiento == null) return "Fecha de vencimiento obligatoria.";
        if (p.precio == null || p.precio.compareTo(BigDecimal.ZERO) < 0 ||
                p.precio.compareTo(new BigDecimal("99999999.99")) > 0 || p.precio.scale() > 2)
            return "Precio inválido (0 a 99999999.99 y hasta 2 decimales).";
        if (p.stock == null || p.stock < 0) return "Stock debe ser un entero no negativo.";
        return null;
    }

    @GET
    public Response listar() {
        String sql = "SELECT id, nombre, proveedor, fecha_vencimiento, precio, stock " +
                     "FROM Producto WHERE eliminado = 0 ORDER BY id";
        List<Producto> productos = new ArrayList<>();
        try (Connection c = dataSource.getConnection();
             PreparedStatement ps = c.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) productos.add(leer(rs));
            return Response.ok(productos).build();
        } catch (SQLException e) {
            return error(500, "No se pudo consultar la base de datos.");
        }
    }

    @GET
    @Path("/{id}")
    public Response buscar(@PathParam("id") int id) {
        if (id <= 0) return error(400, "ID inválido.");
        String sql = "SELECT id, nombre, proveedor, fecha_vencimiento, precio, stock " +
                     "FROM Producto WHERE id = ? AND eliminado = 0";
        try (Connection c = dataSource.getConnection();
             PreparedStatement ps = c.prepareStatement(sql)) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? Response.ok(leer(rs)).build() : error(404, "Producto no encontrado.");
            }
        } catch (SQLException e) {
            return error(500, "No se pudo consultar el producto.");
        }
    }

    @POST
    public Response crear(Producto p) {
        String problema = validar(p);
        if (problema != null) return error(400, problema);
        String sql = "INSERT INTO Producto (nombre, proveedor, fecha_vencimiento, precio, stock) " +
                     "VALUES (?, ?, ?, ?, ?)";
        try (Connection c = dataSource.getConnection();
             PreparedStatement ps = c.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            asignar(ps, p);
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) p.id = rs.getInt(1);
            }
            return Response.status(Response.Status.CREATED).entity(p).build();
        } catch (SQLException e) {
            return error(500, "No se pudo registrar el producto.");
        }
    }

    @PUT
    @Path("/{id}")
    public Response editar(@PathParam("id") int id, Producto p) {
        if (id <= 0) return error(400, "ID inválido.");
        String problema = validar(p);
        if (problema != null) return error(400, problema);
        String sql = "UPDATE Producto SET nombre=?, proveedor=?, fecha_vencimiento=?, precio=?, stock=? " +
                     "WHERE id=? AND eliminado=0";
        try (Connection c = dataSource.getConnection();
             PreparedStatement ps = c.prepareStatement(sql)) {
            asignar(ps, p);
            ps.setInt(6, id);
            if (ps.executeUpdate() == 0) return error(404, "Producto no encontrado.");
            p.id = id;
            return Response.ok(p).build();
        } catch (SQLException e) {
            return error(500, "No se pudo actualizar el producto.");
        }
    }

    @DELETE
    @Path("/{id}")
    public Response eliminar(@PathParam("id") int id) {
        if (id <= 0) return error(400, "ID inválido.");
        String sql = "UPDATE Producto SET eliminado = 1 WHERE id = ? AND eliminado = 0";
        try (Connection c = dataSource.getConnection();
             PreparedStatement ps = c.prepareStatement(sql)) {
            ps.setInt(1, id);
            if (ps.executeUpdate() == 0) return error(404, "Producto no encontrado.");
            return Response.noContent().build();
        } catch (SQLException e) {
            return error(500, "No se pudo eliminar lógicamente el producto.");
        }
    }

    private static void asignar(PreparedStatement ps, Producto p) throws SQLException {
        ps.setString(1, p.nombre.trim());
        ps.setString(2, p.proveedor.trim());
        ps.setDate(3, Date.valueOf(p.fecha_vencimiento));
        ps.setBigDecimal(4, p.precio);
        ps.setInt(5, p.stock);
    }
}
