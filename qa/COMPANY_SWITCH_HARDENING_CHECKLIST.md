# Checklist de Endurecimiento para Cambio de Empresa

## Objetivo

Lograr que cambiar la empresa activa en `sisa.ui` haga un switch real de contexto operativo, sin mezclar datos de otra empresa, sin rutas colgadas y sin depender de reinicios manuales.

La meta funcional es esta:

```text
empresa A activa
  -> usuario elige empresa B
  -> se limpian rutas y estado dependiente de A
  -> se persiste selected-company-id = B
  -> bootstrap bloqueante de B
  -> pull incremental/checkpoint de B
  -> contexts y queries vuelven a hidratar contra B
  -> Home y modulos muestran solo datos de B
```

## Estado de uso

- `[ ]` pendiente
- `[~]` en progreso
- `[x]` completado
- `[!]` bloqueado o con deuda conocida

## Referencias base

- Plan general: `QA_ROADMAP.md`
- Estado de sesion: `QA_STATUS.md`
- Contrato de regresion general: `qa/REGRESSION_CHECKLIST.md`
- Plan de startup previo: `qa/STARTUP_BOOTSTRAP_SYNC_REFACTOR_PLAN.md`
- Bootstrap UI actual: `sisa.ui/contexts/BootstrapContext.tsx`
- Empresa activa UI: `sisa.ui/hooks/useCachedState.ts`, `sisa.ui/components/BottomNavigationBar.tsx`
- Cambio de empresa UI: `sisa.ui/app/user/CompanyPreferenceScreen.tsx`

---

## 0. Criterios rectores

- [x] La empresa activa debe salir de una unica fuente de verdad: `selected-company-id`.
- [x] El cambio de empresa no debe dejar vistas abiertas con IDs de la empresa anterior.
- [ ] Ninguna lectura company-scoped debe operar con `company_id = null` una vez que la sesion ya tiene empresa activa.
- [ ] Ninguna tabla SQLite company-scoped debe mezclar rows de empresas distintas en la misma query operativa.
- [ ] El bootstrap/pull/checkpoint debe ejecutarse por empresa y no reinyectar datos de otra.
- [ ] El usuario no debe ver datos de la empresa anterior despues de confirmar el switch.

---

## 1. Bugs visibles ya detectados

### 1.1 Rutas colgadas durante el switch

- [~] Corregir warning `Route '/[id]' with param 'id' was specified both in the path and as a param`.
- [~] Identificar todos los `router.push` / `navigation.pushUnique` que mandan `id` duplicado en path + params.
- [x] Garantizar que el switch de empresa siempre termine en `router.replace('/Home')` o en una ruta neutra.
- [~] Limpiar modal/detalle abierto de empresa anterior antes de habilitar la nueva shell.

### 1.2 Datos mezclados de empresa anterior

- [x] Confirmar por evidencia que hoy algunas vistas quedan mostrando rows de la empresa previa aunque el bootstrap de la nueva termine bien.
- [ ] Identificar modulos donde se observó el síntoma primero: jobs, clientes, catálogos comerciales, pagos, permisos, citas.
- [ ] Dejar evidencia reproducible paso a paso en runbook manual corto.

---

## 2. Fuente unica de empresa activa

### 2.1 Estado global

- [x] `selected-company-id` ya se propaga entre consumers vivos.
- [~] Auditar que no existan otros estados paralelos de empresa activa en providers o screens.
- [ ] Eliminar cualquier fallback que derive empresa desde route params cuando el dato operativo debe venir del estado global.
- [ ] Asegurar que toda pantalla company-scoped se comporte bien si `selected-company-id` cambia en caliente.

### 2.2 Configuracion del usuario

- [x] Existe pantalla dedicada para empresa activa/predeterminada.
- [ ] Verificar que `company_default_id` siempre pertenezca al set de memberships aprobadas.
- [ ] Si la default queda invalida, reasignar a primera membership aprobada y registrar warning controlado.

---

## 3. Flujo bloqueante de cambio de empresa

### 3.1 Secuencia UX

- [x] El cambio desde barra inferior pasa por una pantalla intermedia.
- [ ] La pantalla debe explicar claramente que va a recargar datos de la empresa elegida.
- [ ] Al confirmar, bloquear navegación operativa hasta terminar bootstrap + checkpoint de la nueva empresa.
- [ ] Si el switch falla, volver a empresa anterior o dejar estado consistente con error visible y sin datos mezclados.

### 3.2 Control tecnico

- [ ] Introducir `companySwitchInProgress` o equivalente para diferenciar login bootstrap vs switch de empresa.
- [ ] Suspender autosyncs, invalidaciones y fetches no criticos mientras corre el switch.
- [ ] Cancelar o ignorar respuestas en vuelo de la empresa anterior cuando ya se empezó a cambiar a otra.
- [ ] No dejar que `Home` y listados entren con empresa nueva si los contexts todavía contienen cache derivada de la empresa vieja.

---

## 4. Bootstrap, sync y checkpoints por empresa

### 4.1 Bootstrap bloqueante

- [x] `BootstrapContext` ya soporta refresh dirigido por `companyId`.
- [ ] Verificar que todos los pasos criticos del switch usen efectivamente el `companyId` pedido y no un valor stale.
- [ ] Garantizar que startup bootstrap, `jobsBootstrap` y `jobsCheckpoint` persistan diagnostico por empresa.

### 4.2 Checkpoints

- [x] El checkpoint de bootstrap jobs ya se guarda por `company_id`.
- [ ] Verificar que pull incremental, reconcile y verify lean/escriban siempre el checkpoint correcto por empresa.
- [ ] Asegurar que cambiar de empresa no re-use un `after`/checkpoint de otra.

### 4.3 Sync automatico

- [ ] Confirmar que `JobsSyncAutoRunner` nunca arranque con empresa vieja despues de un switch.
- [ ] Confirmar que tracking/app updates/otros syncs no arrastren scope anterior.

---

## 5. Auditoria de datos locales por `company_id`

### 5.1 Inventario de tablas

- [ ] Inventariar tablas SQLite que representan entidades company-scoped.
- [ ] Marcar cuales ya tienen `company_id`, cuales dependen de joins y cuales hoy no lo guardan.
- [ ] Detectar tablas auxiliares/snapshots que necesitan scoping por empresa aunque no sean entidad de negocio directa.

### 5.2 Persistencia correcta

- [ ] Validar que todas las escrituras offline de jobs/worklogs/job_items/appointments persistan `company_id` correcto.
- [ ] Validar referencias: clients, folders, statuses, tariffs, providers, categories, products_services, payment_templates.
- [ ] Validar entidades operativas: cash_boxes, payments, receipts, invoices, invoice_items.
- [ ] Validar snapshots/sync state locales donde el `company_id` participa del aislamiento.

### 5.3 Indices y migraciones

- [ ] Agregar migraciones donde falte `company_id` o índices por `company_id` + `updated_at` / `uuid`.
- [ ] Revisar performance de queries filtradas por empresa para que el switch no degrade demasiado.

---

## 6. Auditoria de queries y repositories

### 6.1 Lecturas SQLite

- [ ] Revisar cada repository/list hook para confirmar que filtra por `company_id` cuando corresponde.
- [ ] Identificar queries legacy que hoy usan `companyId ?? null` y terminan trayendo datos globales o mezclados.
- [ ] Revisar listados que hacen `SELECT *` sin scope duro.

### 6.2 Contextos React

- [~] Auditar `StatusesContext`, `ClientsContext`, `FoldersContext`, `ProvidersContext`, `CategoriesContext`, `ProductsServicesContext`, `TariffsContext`, `PaymentTemplatesContext`.
- [~] Auditar `Jobs`, `Appointments`, `Payments`, `Receipts`, `Invoices`, `CashBoxes`, `JobPriorities`.
- [ ] Confirmar que al cambiar `selected-company-id` resetean estado derivado y recargan desde la empresa nueva.

### 6.3 Escrituras y validaciones

- [ ] Confirmar que cada `create/update/delete` valida la empresa activa antes de persistir.
- [ ] Confirmar que la cola de sync local no mezcla operaciones de empresas distintas sin `company_id` correcto.

---

## 7. Limpieza de estado al cambiar empresa

- [ ] Resetear filtros abiertos, búsquedas, selección de tabs y drafts cuyo contenido dependa de la empresa anterior.
- [ ] Invalidar caches de memoria derivados de empresa anterior si no pueden convivir correctamente.
- [ ] Mantener solo caches globales del usuario/dispositivo: perfil, config general, memberships, member_companies, device metadata.
- [ ] Limpiar selección activa de entidad abierta (`job`, `client`, `invoice`, etc.) si no pertenece a la nueva empresa.

---

## 8. Pruebas requeridas

### 8.1 Manuales

- [ ] Login con empresa default A -> verificar que `Home` y módulos cargan datos de A.
- [ ] Cambiar a empresa B desde barra inferior -> confirmar pantalla intermedia -> esperar recarga -> verificar datos solo de B.
- [ ] Volver de B a A en la misma sesión -> verificar que no quedan rows de B en listados de A.
- [ ] Crear dato offline en A -> cambiar a B -> confirmar que no aparece en B -> volver a A -> confirmar que sí aparece.
- [ ] Repetir con catálogos comerciales, jobs, clients y pagos.

### 8.2 Automatizables

- [ ] Smoke que verifique que el switch termina con `selected-company-id` correcto y sin `no-selected-company` en autosync.
- [ ] Smoke que falle si una query company-scoped devuelve rows de otra empresa.
- [ ] Smoke de ruta para asegurar que el cambio de empresa no deja params `id` duplicados o rutas colgadas.

---

## 9. Criterio de terminado

- [ ] Cambiar de empresa desde barra inferior recarga datos de la nueva empresa sin alertas ni warnings de ruta.
- [ ] `Home`, catálogos, jobs, pagos y citas muestran solo datos de la empresa activa.
- [ ] Toda lectura/escritura local relevante queda aislada por `company_id` o clasificada explícitamente como global.
- [ ] Bootstrap, pull incremental y checkpoints quedan confirmados por empresa.
- [ ] Existe smoke/checklist suficiente para no reabrir este problema a ciegas.

---

## Hallazgos del primer slice

- [x] `companies/view` y `companies/memberships` usaban `?id=` genérico sobre rutas estáticas dentro del mismo árbol que `companies/[id]`; se migró a `companyId` para reducir ambigüedad de params.
- [x] `companies/[id]` reaccionaba con alerta visible si la empresa ya no existía en el contexto actual; se cambió a salida silenciosa hacia `/companies` para no tratar un switch válido como error del usuario.
- [x] `ClientsContext`, `ProvidersContext` y `FoldersContext` estaban rehidratando desde SQLite/cache global sin filtrar por empresa activa; se corrigió publicación/fetch inicial para recortar por `selected-company-id`.
- [x] `referenceCache.mergeFoldersCache` estaba persistiendo `company_id: null` en carpetas bootstrap/sync; se corrigió para preservar el scope real de la empresa.
- [!] Sigue pendiente auditar `JobsContext`, `CategoriesContext`, `JobPrioritiesContext` y otros contextos con caches globales que aún podrían dejar flashes o mezclas cross-company.
