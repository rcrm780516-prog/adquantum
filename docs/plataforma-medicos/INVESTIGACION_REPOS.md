# Plataforma de médicos (nombre provisional: Med's Anatomy)
## Investigación de repositorios open source y arquitectura propuesta

Fecha: 2026-10-07 · Autor: Virtuoso Growth Marketing

---

## 1. Resumen

El modelo: suscripción anual de entrada (**$500 MXN/año**) → posicionamiento SEO local con la ficha de Google + perfil en un directorio médico tipo Doctoralia + herramientas simples de IA → **upgrade** a planes de marketing de Virtuoso.

No hace falta construir todo desde cero. Casi cada módulo tiene un proyecto open source maduro que se puede usar o tomar como referencia. Lo que **no** existe hecho, y es nuestra ventaja, es la capa que lo une todo: directorio, reseñas, ficha de Google y embudo de upgrade a la agencia.

---

## 2. Repositorios recomendados por módulo

| Módulo | Repositorio | Licencia | Uso recomendado |
|---|---|---|---|
| **Citas / agenda** | [calcom/cal.com](https://github.com/calcom/cal.com) (~41k ★) | AGPL-3.0 (núcleo) | Motor de agenda autoalojado: disponibilidad, sincronización con Google Calendar, widgets embebibles y API. Es la opción más robusta. |
| Citas (alternativa ligera) | [alextselegidis/easyappointments](https://github.com/alextselegidis/easyappointments) (~4k ★) | GPL-3.0 | Más simple (PHP), pensado para salud y servicios, con sincronización con Google Calendar. Bueno para un MVP rápido. |
| **Directorio + SEO** | [nolly-studio/cult-directory-template](https://github.com/nolly-studio/cult-directory-template) | MIT | Base Next.js + Supabase + shadcn para el directorio de médicos (filtros por especialidad y ciudad, autenticación). |
| Directorio (alternativa) | Mkdirs (Next.js + Stripe + Sanity) | Comercial / ver licencia | Incluye envíos de pago, CMS y SEO. Sirve de referencia para el flujo de suscripción. |
| **Reseñas Google (API)** | [satheeshds/gbp-review-agent](https://github.com/satheeshds/gbp-review-agent) | ver repo | Servidor MCP que lee y responde reseñas de Google Business Profile con IA. Encaja con nuestro stack de Claude. |
| Reseñas (referencia) | [reputemap](https://github.com/reputemap) | ver repo | API + MCP de gestión de reseñas para agencias. Referencia del modelo de agencia. |
| **Editor de anuncios (tipo Canva)** | OpenPolotno (npm `openpolotno`) | Open source | Editor React compatible con plantillas Polotno: multipágina, texto, imágenes, exportación. Ideal para plantillas por especialidad. |
| Editor (base propia) | [fabricjs/fabric.js](https://github.com/fabricjs/fabric.js) o Konva.js | MIT | Si preferimos un editor propio y más simple (plantilla + foto + texto + logo → PNG). |
| **WhatsApp (recordatorios)** | [EvolutionAPI/evolution-api](https://github.com/EvolutionAPI/evolution-api) | Apache-2.0 | Recordatorios y confirmación de citas. ⚠️ Usa WhatsApp no oficial (Baileys): riesgo de bloqueo del número. Para producción conviene la **WhatsApp Cloud API oficial** de Meta. |
| **Expediente / backend clínico (futuro)** | [medplum/medplum](https://github.com/medplum/medplum) (~2.5k ★) | Apache-2.0 | Backend "headless" FHIR con portal de paciente y agenda de ejemplo. Solo si después queremos ofrecer expediente. |
| Expediente (alternativa) | [openemr/openemr](https://github.com/openemr/openemr) | GPL-3.0 | EHR completo. Es pesado; lo menciono solo como referencia. |

**Ya tenemos en este repo (AdQuantum):** `modules/creative_gen.py` (generación de creativos), `modules/brief_processor.py`, integración con Claude/Gemini, Meta y Google Ads. Reutilizar esto para la parte de IA de la plataforma nos ahorra mucho trabajo.

---

## 3. ⚠️ Dos puntos legales que cambian el diseño

### 3.1 Reseñas: NO filtrar "solo las buenas a Google" (review gating)
La política de Google prohíbe expresamente *"desalentar o prohibir reseñas negativas, o solicitar selectivamente reseñas positivas"*. Si un sistema manda solo a los pacientes contentos a Google y desvía a los inconformes, Google puede **borrar reseñas o todo el historial de la ficha**. Eso destruiría justo el producto que vendemos.

**Diseño que sí cumple:**
1. Después de la cita, a **todos** los pacientes se les pide calificar en nuestra plataforma (estrellas + comentario). Esa reseña se publica en el perfil de Med's Anatomy.
2. A **todos**, sin importar la calificación, se les muestra el mismo botón: *"¿Nos ayudas también en Google?"* con el enlace directo a escribir reseña.
3. Las calificaciones bajas generan además una **alerta privada al médico** (para atender al paciente), pero no se oculta el botón de Google.
4. El médico responde reseñas de Google desde nuestro panel, con respuestas sugeridas por IA (API de Google Business Profile v4, `reviews`).

> Técnicamente no es posible "enviar" la reseña de nuestra plataforma a Google: Google solo acepta reseñas escritas por el usuario desde su propia cuenta. Lo que automatizamos es la **invitación** y la **respuesta**.

### 3.2 Publicidad en salud (COFEPRIS) e IA
- Los anuncios de servicios médicos en México están regulados (Reglamento de la Ley General de Salud en materia de Publicidad). Las plantillas y copys generados deben incluir cédula profesional y evitar promesas de resultados, antes/después engañosos, etc. Conviene integrar las reglas del skill `virtuoso-especialista-nichos-salud` como filtro del generador de copys.
- La "asesoría IA" es **solo de marketing para el médico**, nunca consejo clínico a pacientes.
- Datos de pacientes (nombre, teléfono, motivo de cita): aviso de privacidad conforme a la **LFPDPPP** y no guardar datos clínicos en el MVP.

---

## 4. Arquitectura propuesta (MVP)

```
Paciente ──► Directorio (Next.js, SEO: schema Physician/MedicalClinic, páginas por especialidad+ciudad)
               │
               ├── Perfil del médico ── Agendar cita (Cal.com embebido / API)
               │                         └─► Recordatorio WhatsApp (Cloud API)
               └── Calificar ──► Reseña interna + invitación a Google (igual para todos)

Médico ──► Panel
               ├── Ficha de Google (API GBP: datos, fotos, publicaciones, reseñas)
               ├── Estudio de anuncios (plantillas por especialidad, editor Fabric/OpenPolotno)
               ├── Copys con IA (Claude, con filtro COFEPRIS)  ◄── reutiliza AdQuantum
               ├── Asesor IA "lite" (tips de marketing, límite de uso)
               └── Botón "Hablar con Virtuoso" / upgrade de plan  ◄── embudo de venta
```

**Stack sugerido:** Next.js + Supabase (auth, Postgres, storage) para el front y el directorio; el FastAPI existente de AdQuantum como servicio de IA y creativos; Stripe o Mercado Pago para las suscripciones (en México, Mercado Pago + OXXO aumenta la conversión).

---

## 5. Modelo de planes (propuesta para discutir)

| Plan | Precio | Incluye | Objetivo |
|---|---|---|---|
| **Básico** | $500 MXN/año | Perfil en el directorio, agenda, reseñas, optimización inicial de la ficha de Google (plantilla), 5 creativos/mes, IA lite | Captar volumen |
| **Pro** | ~$499–899 MXN/mes | Publicaciones automáticas en la ficha de Google, respuestas IA a reseñas, creativos ilimitados, reporte mensual | Autoservicio rentable |
| **Virtuoso** | Plan de agencia | Meta/Google Ads gestionados (AdQuantum), contenido, web | Ingreso principal |

**Ojo con la economía:** a $500/año (~$42/mes) no alcanza para optimizar fichas de Google a mano. La optimización debe ser **90% automatizada** (formulario de onboarding → IA genera descripción, categorías, servicios y publicaciones → se aplica vía API) o el plan básico perderá dinero. Hay que medir la conversión Básico → Pro/Virtuoso desde el día 1; esa conversión es lo que hace viable el plan de $500.

---

## 6. Sobre el nombre

"Med's Anatomy" es original (no encontré una marca de salud registrada con ese nombre), pero tiene tres problemas:
- El apóstrofo complica el dominio, los hashtags y la escritura en español ("meds anatomy", "medsanatomy").
- Suena a contenido educativo de anatomía, no a "encuentra a tu médico".
- No incluye palabras que el paciente busca (médico, doctor, cita, salud).

Alternativas (verificar dominio `.mx`/`.com` y registro en el IMPI antes de decidir):
- **Consulta Virtuosa**: conecta con la marca Virtuoso.
- **MédicoTop** / **TopMédico**: directo, orientado a búsqueda.
- **Agenda Médica MX**: descriptivo y bueno para SEO.
- **Vitalia**: corto y fácil de recordar.
- **DocVirtuoso**: refuerza el embudo hacia la agencia.

Si quieres conservar "Anatomy", **MedAnatomy** (sin apóstrofo) es más limpio.

---

## 7. Próximos pasos sugeridos

1. Validar con 10–20 médicos actuales o prospectos: ¿pagarían $500/año? ¿Qué módulo valoran más?
2. Decidir el nombre y registrar el dominio y la marca.
3. MVP en 4–6 semanas: directorio + perfil + agenda (Cal.com) + reseñas cumplidas + 10 plantillas de anuncios por especialidad.
4. Fase 2: API de Google Business Profile (requiere solicitar acceso a Google) + respuestas IA + recordatorios por WhatsApp.
5. Fase 3: panel de upgrade conectado a AdQuantum.

---

### Fuentes
- Cal.com vs Easy!Appointments: https://openalternative.co/compare/cal-com/vs/easy-appointments
- GBP Review Agent: https://github.com/satheeshds/gbp-review-agent · ReputeMap: https://github.com/reputemap
- API de reseñas de Google: https://wiserreview.com/blog/google-business-reviews-api/
- Cult Directory Template: https://awesome.ecosyste.ms/projects/github.com%2Fnolly-studio%2Fcult-directory-template
- OpenPolotno: https://cdn.jsdelivr.net/npm/openpolotno@1.0.2/README.md
- Editor Konva/Next.js: https://techblog.geekyants.com/building-a-production-ready-canva-like-editor-with-konva-js-react-19-and-next-js-15.md
- Evolution API: https://railway.com/deploy/evolution-whatsapp-api.md
- Medplum: https://www.medplum.com/open-source
- Política de review gating: https://www.vendasta.com/blog/review-gating/ · https://wiserreview.com/blog/review-gating/ · https://support.google.com/business/thread/216408567/review-gating-clarification?hl=en
