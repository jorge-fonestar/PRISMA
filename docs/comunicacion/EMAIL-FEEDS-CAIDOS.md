# Email a medios con el feed RSS caído

> **Propósito distinto al opt-out.** El email de [EMAIL-MEDIOS-OPTOUT.md](EMAIL-MEDIOS-OPTOUT.md)
> pide *permiso* para extraer contenido. **Este** pregunta por la *disponibilidad técnica del
> feed*: cuando un medio que queremos monitorizar tiene el RSS roto o retirado, le consultamos
> si hay un feed alternativo o está prevista su restitución.

## Plantilla

> **Asunto:** Consulta sobre el RSS de [Medio]
>
> Hola:
>
> Soy responsable de **PolarPrisma** (polarprisma.org), un proyecto sin ánimo de lucro que
> cartografía el pluralismo informativo citando siempre la fuente original con enlace directo.
> Incluimos a [Medio] en nuestro mapa de fuentes.
>
> Hemos detectado que vuestro feed RSS no está accesible ([detalle concreto: p. ej. "error 500
> en efe.com/feed/", "bloqueo 403", "RSS retirado"]). ¿Existe algún **feed alternativo** que
> podamos usar, o está prevista su restitución? Nos basta con titulares + enlace + fecha;
> respetamos robots.txt y usamos un user-agent identificable (PolarPrismaBot/1.0).
>
> Gracias por vuestro tiempo.
> — [Tu nombre], PolarPrisma

## Contactos

| Medio | Vía de contacto | Detalle técnico del fallo |
|---|---|---|
| EFE | efe.com/contacto · comunicacion@efe.com | 500 (`wp_die`) en todos los feeds de efe.com; API tras challenge anti-bots |
| CTXT | info@ctxt.es | Feed bloqueado a nivel WAF (403 incluso con UA de navegador) |
| Público | publico.es/contacto | RSS retirado (redirige a 404) |
| InfoLibre | infolibre.es/contacto | RSS retirado (404) |

## Registro de envíos

| Medio | Fecha envío | Estado |
|---|---|---|
| EFE | 2026-07-31 | Enviado — sin respuesta |
| CTXT | 2026-07-31 | Enviado — sin respuesta |
| Público | — | Pendiente |
| InfoLibre | — | Pendiente |

> El chequeo semanal (`feed_check.php`) sonda automáticamente estos feeds (`url_candidata` en
> `config.php`) y avisa en el panel/Telegram si alguno vuelve, con o sin respuesta al email.
