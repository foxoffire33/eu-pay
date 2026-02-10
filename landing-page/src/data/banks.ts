export interface CountryBanks {
  country: string;
  flag: string;
  banks: string[];
}

export const bankData: CountryBanks[] = [
  {
    country: 'Netherlands',
    flag: 'NL',
    banks: [
      'Rabobank',
      'ING',
      'ABN AMRO',
      'SNS',
      'ASN',
      'bunq',
      'Triodos',
      'Knab',
      'RegioBank',
    ],
  },
  {
    country: 'Germany',
    flag: 'DE',
    banks: [
      'Deutsche Bank',
      'Commerzbank',
      'HypoVereinsbank',
      'ING-DiBa',
      'N26',
      'Sparkasse',
    ],
  },
  {
    country: 'France',
    flag: 'FR',
    banks: [
      'BNP Paribas',
      'Société Générale',
      'Crédit Agricole',
      'LCL',
      'Crédit Mutuel',
      'La Banque Postale',
    ],
  },
  {
    country: 'Spain',
    flag: 'ES',
    banks: ['CaixaBank', 'Santander', 'BBVA', 'Sabadell', 'Bankinter', 'Unicaja'],
  },
  {
    country: 'Italy',
    flag: 'IT',
    banks: [
      'Intesa Sanpaolo',
      'UniCredit',
      'Banco BPM',
      'BPER',
      'BNL',
      'Crédit Agricole Italia',
    ],
  },
  {
    country: 'Belgium',
    flag: 'BE',
    banks: ['KBC', 'BNP Paribas Fortis', 'Belfius', 'ING Belgium'],
  },
  {
    country: 'Austria',
    flag: 'AT',
    banks: ['Erste Bank', 'Raiffeisen', 'BAWAG', 'UniCredit Austria'],
  },
  {
    country: 'Poland',
    flag: 'PL',
    banks: ['PKO', 'mBank', 'ING Śląski', 'Santander PL', 'Millennium', 'Alior'],
  },
];
