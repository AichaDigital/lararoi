# APIs y Servicios para Verificación de NIF-IVA Intracomunitario

**Última actualización:** Noviembre 2025

## Índice

1. [Introducción](#introducción)
2. [VIES - Comisión Europea (Servicios Oficiales)](#vies---comisión-europea-servicios-oficiales)
4. [Servicios de Terceros](#servicios-de-terceros)
5. [Comparativa y Recomendaciones](#comparativa-y-recomendaciones)
6. [Ejemplos de Implementación](#ejemplos-de-implementación)
7. [Consideraciones Técnicas](#consideraciones-técnicas)

---

## Introducción

Este documento recopila información sobre APIs y servicios disponibles para verificar la validez de números de identificación fiscal (NIF-IVA) de operadores intracomunitarios en la Unión Europea.

### ¿Qué es VIES?

**VIES** (VAT Information Exchange System) es el sistema de intercambio de información sobre IVA de la Comisión Europea que permite verificar la validez de números NIF-IVA de operadores registrados en Estados miembros de la UE.

### Casos de uso

- Validar NIF-IVA de clientes B2B antes de emitir facturas sin IVA
- Verificar proveedores en operaciones intracomunitarias
- Cumplimiento normativo en facturación
- Integración en sistemas ERP/CRM
- Verificación masiva de bases de datos de clientes

---

## VIES - Comisión Europea (Servicios Oficiales)

### 1. API SOAP de VIES (Oficial)

**Estado:** ✅ Oficial, gratuito, documentado

#### Endpoints

```
Producción (WSDL):
https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl

Producción (Endpoint):
http://ec.europa.eu/taxation_customs/vies/services/checkVatService

Test (WSDL):
https://ec.europa.eu/taxation_customs/vies/checkVatTestService.wsdl
```

#### Operaciones Disponibles

**1. checkVat** - Validación básica

```xml
Petición:
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
                  xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">
   <soapenv:Header/>
   <soapenv:Body>
      <urn:checkVat>
         <urn:countryCode>ES</urn:countryCode>
         <urn:vatNumber>B12345678</urn:vatNumber>
      </urn:checkVat>
   </soapenv:Body>
</soapenv:Envelope>
```

```xml
Respuesta:
<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
   <env:Body>
      <ns2:checkVatResponse xmlns:ns2="urn:ec.europa.eu:taxud:vies:services:checkVat:types">
         <ns2:countryCode>ES</ns2:countryCode>
         <ns2:vatNumber>B12345678</ns2:vatNumber>
         <ns2:requestDate>2025-11-14</ns2:requestDate>
         <ns2:valid>true</ns2:valid>
         <ns2:name>EMPRESA EJEMPLO SL</ns2:name>
         <ns2:address>CALLE EJEMPLO 123, 28001 MADRID</ns2:address>
      </ns2:checkVatResponse>
   </env:Body>
</env:Envelope>
```

**2. checkVatApprox** - Validación aproximada con matching

Permite enviar datos del trader para verificar coincidencia:
- Nombre
- Dirección
- Código postal
- Ciudad
- Tipo de empresa

#### Campos de Respuesta

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `countryCode` | String | Código país (2 letras) |
| `vatNumber` | String | Número IVA sin prefijo |
| `requestDate` | Date | Fecha de la consulta |
| `valid` | Boolean | ¿Es válido? |
| `name` | String | Nombre/Razón social (opcional*) |
| `address` | String | Dirección (opcional*) |

*Algunos países (Alemania, Austria) no devuelven estos datos por protección de datos.

#### Códigos de Error (FaultString)

| Código | Descripción |
|--------|-------------|
| `INVALID_INPUT` | Código país inválido o NIF vacío |
| `GLOBAL_MAX_CONCURRENT_REQ` | Máximo de peticiones concurrentes alcanzado |
| `MS_MAX_CONCURRENT_REQ` | Máximo de peticiones para ese Estado miembro |
| `SERVICE_UNAVAILABLE` | Error de red o aplicación |
| `MS_UNAVAILABLE` | Estado miembro no disponible |
| `TIMEOUT` | Timeout en la respuesta |

#### Limitaciones

- **Sin autenticación requerida** (servicio público)
- **Rate limiting**: Límites de concurrencia por IP
- **Disponibilidad**: Puede estar caído temporalmente
- **Velocidad**: Puede ser lento en horas pico
- **Datos**: Algunos países ocultan nombre/dirección

---

### 2. API REST de VIES (No Oficial)

**Estado:** ⚠️ No documentada oficialmente, pero funcional

Este es un endpoint REST descubierto que usa la propia web de VIES. No está documentado oficialmente y podría cambiar.

#### Endpoint Principal

```
https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number
```

**Método:** POST  
**Content-Type:** application/json

```json
Petición:
{
  "countryCode": "ES",
  "vatNumber": "B12345678"
}
```

#### Endpoint Alternativo (GET)

```
https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{COUNTRY}/vat/{VAT_NUMBER}
```

**Ejemplo:**
```
https://ec.europa.eu/taxation_customs/vies/rest-api/ms/ES/vat/B12345678
```

#### Respuesta JSON

```json
{
  "isValid": true,
  "requestDate": "2025-11-14T10:30:00.000Z",
  "userError": "VALID",
  "name": "EMPRESA EJEMPLO SL",
  "address": "CALLE EJEMPLO 123\n28001 MADRID",
  "requestIdentifier": "",
  "vatNumber": "B12345678",
  "viesApproximate": {
    "name": "---",
    "street": "---",
    "postalCode": "---",
    "city": "---",
    "companyType": "---",
    "matchName": 3,
    "matchStreet": 3,
    "matchPostalCode": 3,
    "matchCity": 3,
    "matchCompanyType": 3
  }
}
```

#### Ventajas

- ✅ Más simple que SOAP
- ✅ Respuesta JSON nativa
- ✅ No requiere librerías SOAP
- ✅ Fácil de usar con fetch/curl

#### Desventajas

- ⚠️ No está oficialmente documentada
- ⚠️ Puede cambiar sin previo aviso
- ⚠️ No hay garantías de estabilidad

---

### 3. Interfaz Web Manual

**URL:** https://ec.europa.eu/taxation_customs/vies/

Permite consultas manuales individuales sin necesidad de API.

---


### 1. Web Service de Calidad de Datos Identificativos

**Estado:** ✅ Oficial, requiere certificado digital


#### Características Principales

- **Tipo:** SOAP Web Service
- **Autenticación:** Certificado digital obligatorio
- **Capacidad:** Hasta 10.000 NIF por petición (consulta masiva)
- **Respuesta:** Validación de coincidencia NIF-Nombre

#### Endpoints

```
Con certificado personal/representante:
https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP

Con certificado de sello electrónico:
https://www10.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP
```

#### Certificados Admitidos

- Certificado FNMT (ciudadanos)
- Certificado de empleado público
- Certificado de representante de empresa
- Certificado de sello electrónico (para procesos automatizados)

#### Ejemplo de Petición

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
   <soapenv:Header/>
   <soapenv:Body>
      <vnif:VNifV2Ent>
         <vnif:Contribuyente>
            <vnif:Nif>12345678Z</vnif:Nif>
            <vnif:Nombre>GARCÍA LÓPEZ JUAN</vnif:Nombre>
         </vnif:Contribuyente>
         <vnif:Contribuyente>
            <vnif:Nif>B87654321</vnif:Nif>
            <vnif:Nombre>EMPRESA EJEMPLO SL</vnif:Nombre>
         </vnif:Contribuyente>
      </vnif:VNifV2Ent>
   </soapenv:Body>
</soapenv:Envelope>
```

#### Ejemplo de Respuesta

```xml
<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
   <env:Body>
      <VNifV2Sal>
         <Contribuyente>
            <Nif>12345678Z</Nif>
            <Nombre>GARCIA LOPEZ JUAN</Nombre>
            <Resultado>IDENTIFICADO</Resultado>
         </Contribuyente>
         <Contribuyente>
            <Nif>B87654321</Nif>
            <Nombre>EMPRESA EJEMPLO SL</Nombre>
            <Resultado>IDENTIFICADO</Resultado>
         </Contribuyente>
      </VNifV2Sal>
   </env:Body>
</env:Envelope>
```

#### Códigos de Resultado

| Código | Significado |
|--------|-------------|
| `IDENTIFICADO` | NIF y Nombre coinciden exactamente |
| `NO IDENTIFICADO` | NIF válido pero nombre no coincide |
| `NIF INCORRECTO` | Formato incorrecto o dígito de control erróneo |
| `NO PROCESADO` | Excedido límite de 10.000 NIF |

#### Esquemas XSD

```
Entrada: VNifV2Ent.xsd
Salida: VNifV2Sal.xsd
```


#### Documentación Oficial

**Manual Técnico:**
https://sede.agenciatributaria.gob.es/static_files/Sede/Biblioteca/Manual/Tecnicos/WS/030_036_037/Manual_Tecnico_WS_Masivo_Calidad_Datos_Identificativos.pdf

#### Ventajas

- ✅ Consultas masivas (10K por petición)
- ✅ Valida coincidencia NIF-Nombre
- ✅ Para personas físicas devuelve nombre completo
- ✅ Entorno de integración/pruebas disponible

#### Desventajas

- ⚠️ Requiere certificado digital
- ⚠️ Solo para NIF españoles
- ⚠️ Configuración más compleja
- ⚠️ Proceso de autorización previo

#### Proceso de Alta

1. Solicitar autorización mediante formulario oficial
2. Indicar certificado a usar (para integración y producción)
4. Configurar certificados en aplicación
5. Realizar pruebas en entorno de integración
6. Pasar a producción

### 2. Consulta Web Manual (Con Certificado)

**URL:** https://sede.agenciatributaria.gob.es/

**Ruta:** Declaraciones informativas > Identificación fiscal > Comprobación de un NIF de terceros a efectos censales

- Requiere certificado digital
- Consulta individual
- Interfaz web manual
- Gratuito

---

## Servicios de Terceros

### 1. viesapi.eu

**Estado:** 💰 Comercial, con prueba gratuita

**Website:** https://viesapi.eu/

#### Características

- REST API con autenticación por API Key
- Librerías en múltiples lenguajes (Python, PHP, Java, .NET, Node.js, Go)
- Excel Add-in
- Aplicaciones standalone (Windows/Linux/macOS)
- CLI/CMD tools
- Caché inteligente
- Monitorización automática de cambios

#### Planes y Precios

| Plan | Consultas/mes | Precio | Características |
|------|---------------|--------|-----------------|
| Test | Limitado | Gratis | Para pruebas |
| Starter | 500 | ~€9/mes | Básico |
| Business | 5,000 | ~€29/mes | + Soporte |
| Professional | 25,000 | ~€99/mes | + Monitorización |
| Enterprise | Ilimitado | Contactar | SLA, soporte prioritario |

#### Endpoint REST API

```
https://viesapi.eu/api/check/{API_KEY}/{COUNTRY_CODE}/{VAT_NUMBER}
```

#### Ejemplo de Uso

```bash
curl "https://viesapi.eu/api/check/YOUR_API_KEY/ES/B12345678"
```

```json
Respuesta:
{
  "valid": true,
  "countryCode": "ES",
  "vatNumber": "B12345678",
  "name": "EMPRESA EJEMPLO SL",
  "address": "CALLE EJEMPLO 123, 28001 MADRID"
}
```

#### Ventajas

- ✅ Alta disponibilidad y fiabilidad
- ✅ Caché para reducir latencia
- ✅ Múltiples herramientas (Excel, CLI, librerías)
- ✅ Soporte técnico en planes de pago
- ✅ Monitorización de cambios
- ✅ Documentación completa

#### Desventajas

- ⚠️ Servicio de pago (aunque tiene test gratuito)
- ⚠️ Dependencia de tercero

---

### 2. vatlayer.com (APILayer)

**Estado:** 💰 Freemium

**Website:** https://vatlayer.com/

#### Características

- JSON REST API
- 100 peticiones/mes gratis
- HTTPS en planes de pago
- Cálculo de IVA
- Conversión de precios con IVA
- Tipos de IVA por país

#### Planes

| Plan | Consultas/mes | Precio | HTTPS |
|------|---------------|--------|-------|
| Free | 100 | Gratis | ❌ |
| Basic | 1,000 | $9.99/mes | ✅ |
| Professional | 10,000 | $39.99/mes | ✅ |
| Business | 100,000 | $99.99/mes | ✅ |

#### Endpoint

```
http://apilayer.net/api/validate
  ?access_key=YOUR_ACCESS_KEY
  &vat_number=LU26375245
```

#### Ejemplo de Respuesta

```json
{
  "valid": true,
  "database": "ok",
  "format_valid": true,
  "query": "LU26375245",
  "country_code": "LU",
  "vat_number": "26375245",
  "company_name": "AMAZON EUROPE CORE S.A R.L.",
  "company_address": "5, RUE PLAETIS L-2338 LUXEMBOURG"
}
```

#### Otras Funciones

**1. Obtener tipos de IVA por país**
```
http://apilayer.net/api/rate_list?access_key=YOUR_KEY
```

**2. Calcular precio con IVA**
```
http://apilayer.net/api/price
  ?access_key=YOUR_KEY
  &amount=100
  &country_code=ES
```

#### Ventajas

- ✅ Plan gratuito disponible
- ✅ Fácil de usar
- ✅ JSON moderno
- ✅ Funciones adicionales (cálculo IVA, tipos)
- ✅ Documentación clara

#### Desventajas

- ⚠️ Plan gratuito limitado (100 req/mes)
- ⚠️ Sin HTTPS en plan gratuito
- ⚠️ Dependencia de tercero

---

### 3. isvat.eu

**Estado:** 🆓 Gratuito con límites

**Website:** https://www.isvat.eu/

#### Características

- REST API simple
- 100 consultas/mes gratis
- Caché de 14 días
- Sin necesidad de API Key para consultas básicas

#### Endpoints

**Con caché (14 días):**
```
https://www.isvat.eu/{COUNTRY_CODE}/{VAT_NUMBER}
```

**En tiempo real:**
```
https://www.isvat.eu/live/{COUNTRY_CODE}/{VAT_NUMBER}
```

#### Ejemplo

```bash
curl "https://www.isvat.eu/ES/B12345678"
```

```json
{
  "valid": true,
  "countryCode": "ES",
  "vatNumber": "B12345678",
  "name": "EMPRESA EJEMPLO SL",
  "address": "CALLE EJEMPLO 123, MADRID"
}
```

#### Ventajas

- ✅ Completamente gratuito (con límite)
- ✅ Sin API Key para uso básico
- ✅ Muy simple de usar

#### Desventajas

- ⚠️ Límite de 100 req/mes
- ⚠️ Caché puede estar desactualizado
- ⚠️ Sin garantías de disponibilidad
- ⚠️ Sin soporte

---

### 4. Vatstack

**Estado:** 💰 Enterprise

**Website:** https://www.vatstack.com/

#### Características

- API REST completa
- Validación VAT
- Tipos IVA en tiempo real
- Informes automáticos
- Compliance B2B
- Integración con Stripe/Paddle

#### Funcionalidades

- Validación de NIF-IVA
- Detección automática B2B vs B2C
- Cálculo automático de IVA
- Informes para declaraciones
- Webhooks
- Sandbox

#### Precios

Desde $49/mes - Contactar para Enterprise

#### Ventajas

- ✅ Solución todo-en-uno
- ✅ Informes automáticos
- ✅ Integraciones con plataformas de pago
- ✅ SLA garantizado
- ✅ Soporte premium

#### Desventajas

- ⚠️ Precio elevado
- ⚠️ Orientado a empresas grandes

---

## Comparativa y Recomendaciones

### Tabla Comparativa

| Servicio | Tipo | Precio | Auth | Límites | Soporte | Fiabilidad |
|----------|------|--------|------|---------|---------|------------|
| **VIES SOAP** | Oficial | Gratis | No | Concurrencia | ❌ | ⭐⭐⭐ |
| **VIES REST** | No oficial | Gratis | No | Concurrencia | ❌ | ⭐⭐ |
| **viesapi.eu** | Tercero | Desde €9 | API Key | Por plan | ✅ | ⭐⭐⭐⭐⭐ |
| **vatlayer** | Tercero | Desde $0 | API Key | 100-100K | Limited | ⭐⭐⭐⭐ |
| **isvat.eu** | Tercero | Gratis | No | 100/mes | ❌ | ⭐⭐⭐ |
| **Vatstack** | Tercero | Desde $49 | API Key | Alto | ✅✅ | ⭐⭐⭐⭐⭐ |

### Recomendaciones por Caso de Uso

#### 1. Uso Ocasional (< 100 consultas/mes)

**Opción A: VIES REST no oficial + fallback SOAP**
```
Pros: Gratis, simple
Contras: Sin garantías
```

**Opción B: isvat.eu**
```
Pros: Gratis, simple, REST
Contras: Límite 100/mes
```

#### 2. Startup / Negocio Pequeño (< 1,000 consultas/mes)

**Recomendado: vatlayer.com (plan básico)**
```
Pros: $9.99/mes, JSON, 1K consultas, HTTPS
Contras: Dependencia tercero
Ideal para: eCommerce pequeño, SaaS inicial
```

#### 3. Negocio Medio (1,000 - 10,000 consultas/mes)

**Recomendado: viesapi.eu (plan Business)**
```
Pros: €29/mes, 5K consultas, librerías, soporte
Contras: -
Ideal para: Plataformas de facturación, ERP
```

#### 4. Negocio Grande (> 10,000 consultas/mes)

**Opción A: viesapi.eu (Professional/Enterprise)**
```
Pros: Monitorización, SLA, soporte prioritario
Ideal para: Grandes ERP, marketplaces
```

**Opción B: Vatstack**
```
Pros: Todo-en-uno, compliance automático
Ideal para: SaaS internacional, empresas con compliance complejo
```

#### 5. Solo Validación NIF Español

```
Pros: Oficial, gratis, hasta 10K/petición
Contras: Requiere certificado, setup complejo
Ideal para: Empresas españolas con infraestructura
```

#### 6. Prototipo / MVP

**Recomendado: VIES REST + vatlayer free**
```
Pros: $0, rápido de implementar
Contras: Límites, sin soporte
Ideal para: Validar idea, demos
```

---

## Ejemplos de Implementación

### 1. JavaScript/Node.js - VIES REST

```javascript
async function validateVAT(countryCode, vatNumber) {
  const url = `https://ec.europa.eu/taxation_customs/vies/rest-api/ms/${countryCode}/vat/${vatNumber}`;
  
  try {
    const response = await fetch(url);
    const data = await response.json();
    
    return {
      valid: data.isValid,
      name: data.name,
      address: data.address,
      error: data.userError !== 'VALID' ? data.userError : null
    };
  } catch (error) {
    console.error('Error validating VAT:', error);
    throw error;
  }
}

// Uso
const result = await validateVAT('ES', 'B12345678');
console.log(result);
```

### 2. Python - VIES SOAP

```python
from zeep import Client

def validate_vat_soap(country_code, vat_number):
    wsdl = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl'
    client = Client(wsdl)
    
    try:
        result = client.service.checkVat(country_code, vat_number)
        return {
            'valid': result.valid,
            'name': result.name if result.name else None,
            'address': result.address if result.address else None,
            'country_code': result.countryCode,
            'vat_number': result.vatNumber,
            'request_date': result.requestDate
        }
    except Exception as e:
        return {'valid': False, 'error': str(e)}

# Uso
result = validate_vat_soap('ES', 'B12345678')
print(result)
```

### 3. PHP - vatlayer API

```php
<?php
function validateVATLayer($vatNumber, $apiKey) {
    $url = "http://apilayer.net/api/validate";
    $params = [
        'access_key' => $apiKey,
        'vat_number' => $vatNumber
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Uso
$result = validateVATLayer('LU26375245', 'YOUR_API_KEY');
var_dump($result);
?>
```


```python
from zeep import Client
from zeep.wsse.signature import Signature
from lxml import etree

    wsdl = 'https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP?wsdl'
    
    # Configurar certificado
    session = Session()
    session.cert = (cert_path, key_path)
    
    transport = Transport(session=session)
    client = Client(wsdl, transport=transport)
    
    # Crear petición
    contribuyente = {
        'Nif': nif,
        'Nombre': nombre.upper()
    }
    
    try:
        result = client.service.verificar(contribuyente)
        return {
            'nif': result.Nif,
            'nombre': result.Nombre,
            'resultado': result.Resultado  # IDENTIFICADO / NO IDENTIFICADO
        }
    except Exception as e:
        return {'error': str(e)}

# Uso
    '12345678Z', 
    'GARCIA LOPEZ JUAN',
    '/path/to/cert.pem',
    '/path/to/key.pem'
)
print(result)
```

### 5. cURL - VIES REST

```bash
#!/bin/bash

COUNTRY_CODE="ES"
VAT_NUMBER="B12345678"

curl -s "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/${COUNTRY_CODE}/vat/${VAT_NUMBER}" \
  | jq '{valid: .isValid, name: .name, address: .address}'
```

### 6. JavaScript - Estrategia con Fallback

```javascript
class VATValidator {
  constructor() {
    this.providers = [
      this.checkVIESRest,
      this.checkVIESSOAP,
      this.checkVatlayer
    ];
  }

  async validate(countryCode, vatNumber) {
    for (const provider of this.providers) {
      try {
        const result = await provider.call(this, countryCode, vatNumber);
        if (result.success) {
          return result;
        }
      } catch (error) {
        console.warn(`Provider failed: ${provider.name}`, error);
        continue;
      }
    }
    throw new Error('All providers failed');
  }

  async checkVIESRest(countryCode, vatNumber) {
    const url = `https://ec.europa.eu/taxation_customs/vies/rest-api/ms/${countryCode}/vat/${vatNumber}`;
    const response = await fetch(url);
    const data = await response.json();
    
    return {
      success: data.isValid,
      provider: 'VIES_REST',
      data: {
        valid: data.isValid,
        name: data.name,
        address: data.address
      }
    };
  }

  async checkVIESSOAP(countryCode, vatNumber) {
    // Implementación SOAP
    // ...
  }

  async checkVatlayer(countryCode, vatNumber) {
    const apiKey = process.env.VATLAYER_KEY;
    const url = `http://apilayer.net/api/validate?access_key=${apiKey}&vat_number=${countryCode}${vatNumber}`;
    const response = await fetch(url);
    const data = await response.json();
    
    return {
      success: data.valid,
      provider: 'VATLAYER',
      data: data
    };
  }
}

// Uso
const validator = new VATValidator();
const result = await validator.validate('ES', 'B12345678');
console.log(result);
```

---

## Consideraciones Técnicas

### 1. Sincronización VIES

⚠️ **Importante:** Cuando una empresa se da de alta en el ROI, la información **NO está disponible inmediatamente** en VIES.

**Tiempos típicos:**
- Sincronización con VIES: **24-72 horas**
- Algunos países: Hasta **7 días**

**Recomendación:** En aplicaciones críticas, implementar sistema de reintento después de 48-72h.

### 2. Privacidad de Datos

Algunos países **NO devuelven** nombre y dirección por protección de datos:

| País | Devuelve Nombre | Devuelve Dirección |
|------|----------------|-------------------|
| España | ✅ | ✅ |
| Francia | ✅ | ✅ |
| Italia | ✅ | ✅ |
| Alemania | ❌ | ❌ |
| Austria | ❌ | ❌ |
| Bélgica | Parcial | Parcial |

**Respuesta típica Alemania:**
```json
{
  "valid": true,
  "name": "---",
  "address": "---"
}
```

### 3. Disponibilidad y Fiabilidad

**VIES oficial:**
- Disponibilidad: ~95-98%
- Caídas frecuentes: Lunes por la mañana (alto tráfico)
- Mantenimientos: Suelen avisar en la web

**Recomendaciones:**
1. Implementar **caché local** (24-48h válido)
2. Sistema de **fallback** múltiple
3. **Rate limiting** propio (no abusar del servicio)
4. **Retry con backoff exponencial**

### 4. Rate Limiting

VIES oficial implementa límites:
- **Concurrencia:** Máx. peticiones simultáneas por IP
- **No hay límite diario** oficial, pero se recomienda uso razonable

**Buenas prácticas:**
```
- Caché local: 24-48h
- Max 1 petición/segundo por cliente
- Retry después de 1-2-5-10 segundos
- Timeout: 10 segundos
```

### 5. Implementación de Caché

**Ejemplo de estrategia:**

```javascript
class CachedVATValidator {
  constructor(ttl = 86400000) { // 24h
    this.cache = new Map();
    this.ttl = ttl;
  }

  getCacheKey(country, vat) {
    return `${country}-${vat}`;
  }

  async validate(country, vat) {
    const key = this.getCacheKey(country, vat);
    const cached = this.cache.get(key);

    if (cached && Date.now() - cached.timestamp < this.ttl) {
      return { ...cached.data, fromCache: true };
    }

    const result = await this.fetchFromAPI(country, vat);
    
    this.cache.set(key, {
      data: result,
      timestamp: Date.now()
    });

    return { ...result, fromCache: false };
  }

  async fetchFromAPI(country, vat) {
    // Implementación real
  }
}
```

### 6. Manejo de Errores

```javascript
class VATError extends Error {
  constructor(code, message) {
    super(message);
    this.code = code;
    this.name = 'VATError';
  }
}

async function validateWithErrorHandling(country, vat) {
  try {
    const result = await validateVAT(country, vat);
    return result;
  } catch (error) {
    if (error.message.includes('TIMEOUT')) {
      throw new VATError('TIMEOUT', 'Service timeout, please retry');
    } else if (error.message.includes('MS_UNAVAILABLE')) {
      throw new VATError('UNAVAILABLE', `${country} service unavailable`);
    } else {
      throw new VATError('UNKNOWN', error.message);
    }
  }
}
```

### 7. Testing

**Números de prueba VIES:**

Test Service: `https://ec.europa.eu/taxation_customs/vies/checkVatTestService.wsdl`

| VAT Number | Resultado |
|------------|-----------|
| 100 | Válido |
| 200 | Inválido |
| 201 | INVALID_INPUT |
| 300 | SERVICE_UNAVAILABLE |
| 400 | VAT_BLOCKED |
| 500 | GLOBAL_MAX_CONCURRENT_REQ |

**Ejemplo:**
```javascript
// Usa "100" como VAT number en el test service
await validateVAT('ES', '100'); // Devuelve válido
```

### 8. Validación de Formato

**Antes de llamar a la API, validar formato localmente:**

```javascript
const VAT_FORMATS = {
  ES: /^[A-Z][0-9]{8}$|^[0-9]{8}[A-Z]$|^[A-Z][0-9]{7}[A-Z]$/,
  DE: /^[0-9]{9}$/,
  FR: /^[A-Z0-9]{2}[0-9]{9}$/,
  IT: /^[0-9]{11}$/,
  PT: /^[0-9]{9}$/,
  // ... más países
};

function isValidFormat(country, vat) {
  const pattern = VAT_FORMATS[country];
  return pattern ? pattern.test(vat) : true;
}

// Uso
if (!isValidFormat('ES', 'B12345678')) {
  throw new Error('Invalid format');
}
```

### 9. Logging y Auditoría

**Para compliance, registrar:**
- Fecha/hora de consulta
- NIF-IVA consultado
- Resultado (válido/inválido)
- Nombre y dirección devueltos
- RequestIdentifier (si disponible)
- IP del solicitante

**Esto es crítico para:**
- Justificar exención de IVA intracomunitario
- Auditorías fiscales
- Reclamaciones

### 10. Seguridad

**No almacenar:**
- ❌ API Keys en código fuente
- ❌ Certificados en repositorios
- ❌ Logs con datos personales sin anonimizar

**Usar:**
- ✅ Variables de entorno
- ✅ Secrets managers (AWS Secrets, Azure Key Vault)
- ✅ Certificados en HSM o almacenes seguros
- ✅ HTTPS siempre que sea posible

---

## Checklist de Implementación

### Fase 1: Análisis
- [ ] Volumen estimado de consultas/mes
- [ ] Presupuesto disponible
- [ ] Países objetivo
- [ ] Necesidad de datos adicionales (nombre/dirección)
- [ ] Requisitos de compliance

### Fase 2: Selección de Proveedor
- [ ] Proveedor principal seleccionado
- [ ] Proveedor de fallback identificado
- [ ] API Keys/certificados obtenidos
- [ ] Documentación revisada

### Fase 3: Desarrollo
- [ ] Validación de formato local implementada
- [ ] Llamada a API principal implementada
- [ ] Sistema de fallback implementado
- [ ] Caché implementado
- [ ] Manejo de errores robusto
- [ ] Logging y auditoría

### Fase 4: Testing
- [ ] Tests unitarios con mocks
- [ ] Tests con servicio de pruebas
- [ ] Tests con datos reales en sandbox
- [ ] Tests de carga
- [ ] Tests de timeout y errores

### Fase 5: Producción
- [ ] Monitorización configurada
- [ ] Alertas configuradas
- [ ] Rate limiting implementado
- [ ] Documentación para equipo
- [ ] Plan de contingencia definido

---

## Recursos Adicionales

### Documentación Oficial

**Comisión Europea:**
- Web VIES: https://ec.europa.eu/taxation_customs/vies/
- FAQ: https://ec.europa.eu/taxation_customs/vies/faq.html
- Información técnica: https://ec.europa.eu/taxation_customs/vies/technicalInformation.html

- Servicios Web: https://sede.agenciatributaria.gob.es/
- Manual WS: https://sede.agenciatributaria.gob.es/static_files/Sede/Biblioteca/Manual/Tecnicos/WS/

### Librerías y SDKs

**Python:**
- `zeep`: Cliente SOAP
- `pyvies`: Wrapper para VIES
- `stdnum`: Validación de formatos

**JavaScript/Node.js:**
- `soap`: Cliente SOAP
- `vies`: Wrapper VIES
- `axios`: HTTP client

**PHP:**
- `SoapClient`: Built-in
- `dragonbe/vies`: Librería completa

**Java:**
- Apache CXF: Cliente SOAP
- JAX-WS: Built-in

### Herramientas

- **Postman**: Testing de APIs
- **SoapUI**: Testing de servicios SOAP
- **cURL**: Testing rápido desde CLI

---

## Conclusiones

### Recomendaciones Finales

1. **Para la mayoría de casos:** Empezar con VIES REST no oficial + fallback a SOAP
2. **Para producción seria:** viesapi.eu o vatlayer según volumen
4. **Para enterprise:** Vatstack si necesitas compliance todo-en-uno

### Mejores Prácticas

1. ✅ **Siempre** implementar caché (24-48h)
2. ✅ **Siempre** tener fallback
3. ✅ **Siempre** validar formato antes de llamar API
4. ✅ **Siempre** loggear consultas para auditoría
5. ✅ **Nunca** confiar en un solo proveedor
6. ✅ **Nunca** hardcodear API keys
7. ✅ **Nunca** hacer consultas síncronas sin timeout

---

**Documento creado:** Noviembre 2025  
**Autor:** Investigación sobre APIs VAT/NIF-IVA  
**Versión:** 1.0
