// Catálogo de los 32 estados de México (mismos nombres que devuelve el
// catálogo SEPOMEX usado en api/cp.php), compartido entre el formulario de
// vendedores y el mapa por estados del panel admin.

const ESTADOS_MX = [
  'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche',
  'Chiapas', 'Chihuahua', 'Ciudad de México', 'Coahuila de Zaragoza', 'Colima',
  'Durango', 'Guanajuato', 'Guerrero', 'Hidalgo', 'Jalisco', 'México',
  'Michoacán de Ocampo', 'Morelos', 'Nayarit', 'Nuevo León', 'Oaxaca', 'Puebla',
  'Querétaro', 'Quintana Roo', 'San Luis Potosí', 'Sinaloa', 'Sonora',
  'Tabasco', 'Tamaulipas', 'Tlaxcala', 'Veracruz de Ignacio de la Llave',
  'Yucatán', 'Zacatecas',
];

// El GeoJSON de fronteras (angelnmara/geojson, mexicoHigh.json) usa nombres
// cortos e ids ISO 3166-2:MX. Mapeamos por id (más confiable que por nombre,
// que puede venir sin los sufijos oficiales o sin acentos).
const ISO_A_ESTADO_MX = {
  'MX-AGU': 'Aguascalientes',
  'MX-BCN': 'Baja California',
  'MX-BCS': 'Baja California Sur',
  'MX-CAM': 'Campeche',
  'MX-CHP': 'Chiapas',
  'MX-CHH': 'Chihuahua',
  'MX-CMX': 'Ciudad de México',
  'MX-DIF': 'Ciudad de México', // nombre historico (Distrito Federal) en datasets viejos
  'MX-COA': 'Coahuila de Zaragoza',
  'MX-COL': 'Colima',
  'MX-DUR': 'Durango',
  'MX-GUA': 'Guanajuato',
  'MX-GRO': 'Guerrero',
  'MX-HID': 'Hidalgo',
  'MX-JAL': 'Jalisco',
  'MX-MEX': 'México',
  'MX-MIC': 'Michoacán de Ocampo',
  'MX-MOR': 'Morelos',
  'MX-NAY': 'Nayarit',
  'MX-NLE': 'Nuevo León',
  'MX-OAX': 'Oaxaca',
  'MX-PUE': 'Puebla',
  'MX-QUE': 'Querétaro',
  'MX-ROO': 'Quintana Roo',
  'MX-SLP': 'San Luis Potosí',
  'MX-SIN': 'Sinaloa',
  'MX-SON': 'Sonora',
  'MX-TAB': 'Tabasco',
  'MX-TAM': 'Tamaulipas',
  'MX-TLA': 'Tlaxcala',
  'MX-VER': 'Veracruz de Ignacio de la Llave',
  'MX-YUC': 'Yucatán',
  'MX-ZAC': 'Zacatecas',
};
