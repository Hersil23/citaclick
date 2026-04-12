# Generar favicon.ico

El archivo `favicon.ico` debe generarse a partir de `icon-16.svg` e `icon-32.svg`.

## Opcion 1 — CLI con svg2ico

```bash
npx svg2ico icon-16.svg icon-32.svg --output ../../favicon.ico
```

Esto genera un `.ico` multi-resolucion con 16x16 y 32x32 en un solo archivo.

## Opcion 2 — Web

1. Ir a https://realfavicongenerator.net
2. Subir `icon-512.svg` como fuente
3. Configurar las opciones para cada plataforma
4. Descargar el paquete generado
5. Copiar `favicon.ico` a `public/`

## Opcion 3 — ImageMagick

```bash
convert icon-16.svg icon-32.svg favicon.ico
mv favicon.ico ../../favicon.ico
```

## Ubicacion final

El archivo `favicon.ico` debe quedar en `public/favicon.ico` y se referencia en el HTML con:

```html
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/assets/icons/icon-32.svg">
<link rel="apple-touch-icon" href="/assets/icons/icon-180.svg">
```
