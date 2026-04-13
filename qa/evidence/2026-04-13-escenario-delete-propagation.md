# Evidencia Multi-Dispositivo - Delete Propagation Sin Reaparicion

## Estado

- Tipo: AUTOMATIZADO (cubierto por PHPUnit + Smoke)
- Nota: Este escenario ya tiene cobertura automatizada completa

## Cobertura automatizada existente

### Backend (PHPUnit)

- `testApplyStatusDeleteKeepsTombstoneDeviceAndVersion` - Server-side tombstone
- `testPullReturnsDeletedReferenceOperationsWithTombstones` - Pull devuelve tombstones
- `testBootstrapReferencesIncludesDeletedReferenceTombstones` - Bootstrap incluye tombstones
- `testReconcileDetectsDeletedStatusAndProviderMismatches` - Reconcile detecta drift
- `testVerifyIncludesDeletedStatusesAndProvidersInSummaries` - Verify cuenta tombstones

### Frontend (Smoke)

- `sync-smoke.js` valida handling de `job_file`, `job_item_file`, `worklog_file`
- `usePullJobsSync` maneja `detach/delete` y persiste tombstones
- `useBootstrapJobsFromApi` remueve referencias eliminadas del cache

### Mismo coverage para

- `providers` (todas las pruebas equivalentes)
- `clients` (`testApplyClientDeleteUsesSoftDeleteAndKeepsTombstoneVisible`)
- `folders` (`testApplyFolderDeleteUsesSoftDeleteAndKeepsTombstoneVisible`)
- `file_attachments` (múltiples pruebas de delete + detach)

## Resultado

- Estado: PASS (automatizado)
- Baseline: `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> PASS
- PHPUnit: ~60 tests de sync/reconcile/verify/delete pasan
- Smoke: todas las validaciones de client-side tombstone/persistence pasan

## Decision

- Estado: pass
- Accion siguiente: ejecutar corrida manual solo si se requiere validacion E2E real con dispositivos fisicos
