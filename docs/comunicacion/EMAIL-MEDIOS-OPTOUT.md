# Email a medios — notificación con opt-out (borrador)

> **Estado:** Borrador (2026-07-20). Pendiente de que Jorge comparta el email
> original de primavera (que no obtuvo respuestas) para ajustar tono e historial,
> y de la consulta legal previa a escalar la captura de portadas.

## Contexto y enfoque

La ronda de primavera pedía **permiso** y nadie contestó — el silencio de un
buzón genérico de un medio no es un "no", es la papelera. El enfoque nuevo
invierte la carga: **notificación transparente + opt-out fácil**, en lugar de
petición de permiso.

Encaje jurídico del enfoque (a validar en la consulta legal pendiente):

- Prisma usa **titulares, extractos muy breves y enlaces con atribución destacada**,
  mayoritariamente de **feeds RSS que los propios medios publican para su
  sindicación**. No reproduce artículos ni sustituye la visita al medio: enlaza.
- El derecho conexo de los editores de prensa (art. 15 Directiva (UE) 2019/790,
  transpuesto en España por el RDL 24/2021) **excluye expresamente los hipervínculos
  y los "extractos muy breves"** — que es el terreno en el que se mueve Prisma.
- Para el análisis computacional aplica además la excepción de **minería de textos
  y datos** (art. 4 de la misma directiva): lícita sobre contenido accesible
  legalmente **salvo reserva expresa** del titular — exactamente la lógica opt-out
  de este email. Prisma se compromete a respetar cualquier reserva (respuesta a
  este correo, robots.txt o aviso en sus términos).
- El punto delicado no es el RSS sino la **captura de portada** (scraping de
  medios sin feed): no activar con medios de categoría A/B hasta pasar la consulta.

## Borrador

**Asunto (opciones):**
- `Prisma (polarprisma.org) enlaza a [MEDIO] — información y vía de exclusión`
- `Su medio aparece citado y enlazado en polarprisma.org — cómo excluirse si lo desean`

---

Estimado equipo de [MEDIO]:

Les escribo de nuevo desde Prisma (https://polarprisma.org), un proyecto sin
ánimo de lucro, sin publicidad y sin afiliación política que analiza la
polarización informativa en España: mostramos cómo cubren una misma noticia
medios de todo el espectro ideológico, siempre citando y **enlazando a los
artículos originales**.

Les contactamos en primavera y entendemos que estos buzones reciben mucho
volumen, así que esta vez queremos ser lo más concretos posible:

**Qué hacemos con su contenido.** Utilizamos exclusivamente los titulares y
breves extractos de su feed RSS público, con atribución visible de [MEDIO] y
enlace directo a su web en cada mención. No reproducimos artículos, no hay
muro entre el lector y ustedes: cada cita es una puerta a su medio.

**Qué no hacemos.** No copiamos contenido, no lo monetizamos, no entrenamos
modelos con él y no sustituimos su lectura.

**Cómo excluirse.** Si prefieren no aparecer en Prisma, basta con responder a
este correo y retiraremos su medio del sistema en un plazo máximo de 72 horas,
sin preguntas. Respetamos igualmente cualquier reserva de derechos que expresen
en sus términos de uso o robots.txt. Salvo indicación en contra, seguiremos
citándoles y enlazándoles en estas condiciones, al amparo de las excepciones de
hipervínculos y extractos muy breves del RDL 24/2021.

Si en cambio quieren saber más — o explorar que su cobertura aparezca con más
contexto — estaremos encantados de hablar.

Un saludo cordial,
[FIRMA]
Prisma — https://polarprisma.org/presentacion.php · Metodología pública:
https://polarprisma.org/fuentes.php

---

## Notas para la versión final

1. Ajustar el segundo párrafo cuando tengamos el email original (referencia
   honesta al intento anterior sin sonar a reproche).
2. Decidir la firma: el proyecto se presenta como anónimo en la web — ¿firma
   personal, "El equipo de Prisma", o momento de poner nombre?
3. Enviar de forma escalonada (3-4 medios/semana) para poder atender respuestas.
4. Registrar en una tabla simple: medio, fecha envío, respuesta, estado
   (activo / excluido / en conversación).
