<?php

declare(strict_types=1);

namespace App\Service;

/**
 * EU/EEA PSD2 Bank Registry.
 *
 * PSD2 (Directive 2015/2366) is MANDATORY for all payment service providers
 * operating in the EU/EEA since 14 September 2019. Every licensed bank must
 * expose XS2A (Access to Account) APIs for AISP, PISP, and CBPII.
 *
 * This registry covers the major banks across all 27 EU member states + EEA
 * (Norway, Iceland, Liechtenstein). Banks are grouped by country.
 *
 * @see https://www.eba.europa.eu/risk-analysis-and-data/register — EBA register
 * @see https://www.berlin-group.org/ — NextGenPSD2 specification
 */
class EuBankRegistry
{
    /**
     * Get all supported EU/EEA PSD2 banks.
     *
     * @return array<array{bic: string, name: string, country: string, country_name: string}>
     */
    public static function getAll(): array
    {
        return array_merge(
            self::austria(),
            self::belgium(),
            self::bulgaria(),
            self::croatia(),
            self::cyprus(),
            self::czechia(),
            self::denmark(),
            self::estonia(),
            self::finland(),
            self::france(),
            self::germany(),
            self::greece(),
            self::hungary(),
            self::ireland(),
            self::italy(),
            self::latvia(),
            self::lithuania(),
            self::luxembourg(),
            self::malta(),
            self::netherlands(),
            self::poland(),
            self::portugal(),
            self::romania(),
            self::slovakia(),
            self::slovenia(),
            self::spain(),
            self::sweden(),
            // EEA (non-EU)
            self::norway(),
            self::iceland(),
            self::liechtenstein(),
            // Pan-European digital banks
            self::panEuropean(),
        );
    }

    /**
     * Get banks for a specific country.
     *
     * @return array<array{bic: string, name: string, country: string, country_name: string}>
     */
    public static function getByCountry(string $countryCode): array
    {
        $cc = strtoupper($countryCode);
        return array_filter(self::getAll(), fn(array $bank) => $bank['country'] === $cc);
    }

    /**
     * Look up a bank by BIC.
     */
    public static function findByBic(string $bic): ?array
    {
        $bic = strtoupper($bic);
        foreach (self::getAll() as $bank) {
            if ($bank['bic'] === $bic) {
                return $bank;
            }
        }
        return null;
    }

    /**
     * Get all supported country codes.
     *
     * @return string[]
     */
    public static function getSupportedCountries(): array
    {
        return array_values(array_unique(array_column(self::getAll(), 'country')));
    }

    // ── EU Member States ────────────────────────────────

    private static function austria(): array
    {
        return [
            ['bic' => 'BKAUATWW', 'name' => 'UniCredit Bank Austria', 'country' => 'AT', 'country_name' => 'Austria'],
            ['bic' => 'GIBAATWW', 'name' => 'Erste Bank', 'country' => 'AT', 'country_name' => 'Austria'],
            ['bic' => 'RZBAATWW', 'name' => 'Raiffeisen Bank International', 'country' => 'AT', 'country_name' => 'Austria'],
            ['bic' => 'BAWAATWW', 'name' => 'BAWAG P.S.K.', 'country' => 'AT', 'country_name' => 'Austria'],
            ['bic' => 'ABORATWW', 'name' => 'Oberbank', 'country' => 'AT', 'country_name' => 'Austria'],
        ];
    }

    private static function belgium(): array
    {
        return [
            ['bic' => 'KREDBEBB', 'name' => 'KBC Bank', 'country' => 'BE', 'country_name' => 'Belgium'],
            ['bic' => 'GEBABEBB', 'name' => 'BNP Paribas Fortis', 'country' => 'BE', 'country_name' => 'Belgium'],
            ['bic' => 'GKCCBEBB', 'name' => 'Belfius Bank', 'country' => 'BE', 'country_name' => 'Belgium'],
            ['bic' => 'NICABEBB', 'name' => 'ING Belgium', 'country' => 'BE', 'country_name' => 'Belgium'],
            ['bic' => 'AXABBE22', 'name' => 'AXA Bank Belgium', 'country' => 'BE', 'country_name' => 'Belgium'],
        ];
    }

    private static function bulgaria(): array
    {
        return [
            ['bic' => 'UNCRBGSF', 'name' => 'UniCredit Bulbank', 'country' => 'BG', 'country_name' => 'Bulgaria'],
            ['bic' => 'BPBIBGSF', 'name' => 'Postbank (Eurobank Bulgaria)', 'country' => 'BG', 'country_name' => 'Bulgaria'],
            ['bic' => 'SABORGS', 'name' => 'DSK Bank', 'country' => 'BG', 'country_name' => 'Bulgaria'],
            ['bic' => 'FINVBGSF', 'name' => 'First Investment Bank', 'country' => 'BG', 'country_name' => 'Bulgaria'],
        ];
    }

    private static function croatia(): array
    {
        return [
            ['bic' => 'ZABAHR2X', 'name' => 'Zagrebačka banka (UniCredit)', 'country' => 'HR', 'country_name' => 'Croatia'],
            ['bic' => 'PBZGHR2X', 'name' => 'Privredna banka Zagreb', 'country' => 'HR', 'country_name' => 'Croatia'],
            ['bic' => 'RZBHHR2X', 'name' => 'Raiffeisenbank Austria d.d.', 'country' => 'HR', 'country_name' => 'Croatia'],
            ['bic' => 'EABORHR2X', 'name' => 'Erste & Steiermärkische Bank', 'country' => 'HR', 'country_name' => 'Croatia'],
        ];
    }

    private static function cyprus(): array
    {
        return [
            ['bic' => 'BCYPCY2N', 'name' => 'Bank of Cyprus', 'country' => 'CY', 'country_name' => 'Cyprus'],
            ['bic' => 'HEBACY2N', 'name' => 'Hellenic Bank', 'country' => 'CY', 'country_name' => 'Cyprus'],
            ['bic' => 'AIKBCY2N', 'name' => 'Astrobank', 'country' => 'CY', 'country_name' => 'Cyprus'],
        ];
    }

    private static function czechia(): array
    {
        return [
            ['bic' => 'KOMBCZPP', 'name' => 'Komerční banka', 'country' => 'CZ', 'country_name' => 'Czechia'],
            ['bic' => 'CABORCZP', 'name' => 'ČSOB', 'country' => 'CZ', 'country_name' => 'Czechia'],
            ['bic' => 'GIBACZPX', 'name' => 'Česká spořitelna (Erste)', 'country' => 'CZ', 'country_name' => 'Czechia'],
            ['bic' => 'FIOBCZPP', 'name' => 'Fio banka', 'country' => 'CZ', 'country_name' => 'Czechia'],
            ['bic' => 'BACXCZPP', 'name' => 'UniCredit Bank CZ', 'country' => 'CZ', 'country_name' => 'Czechia'],
        ];
    }

    private static function denmark(): array
    {
        return [
            ['bic' => 'DABADKKK', 'name' => 'Danske Bank', 'country' => 'DK', 'country_name' => 'Denmark'],
            ['bic' => 'NDEADKKK', 'name' => 'Nordea Danmark', 'country' => 'DK', 'country_name' => 'Denmark'],
            ['bic' => 'JYBADKKK', 'name' => 'Jyske Bank', 'country' => 'DK', 'country_name' => 'Denmark'],
            ['bic' => 'SABORDDKK', 'name' => 'Sydbank', 'country' => 'DK', 'country_name' => 'Denmark'],
            ['bic' => 'NYKBDKKK', 'name' => 'Nykredit Bank', 'country' => 'DK', 'country_name' => 'Denmark'],
        ];
    }

    private static function estonia(): array
    {
        return [
            ['bic' => 'HABAEE2X', 'name' => 'Swedbank Estonia', 'country' => 'EE', 'country_name' => 'Estonia'],
            ['bic' => 'EEUHEE2X', 'name' => 'SEB Estonia', 'country' => 'EE', 'country_name' => 'Estonia'],
            ['bic' => 'LHVBEE22', 'name' => 'LHV Pank', 'country' => 'EE', 'country_name' => 'Estonia'],
            ['bic' => 'RIKOEE22', 'name' => 'Luminor Bank Estonia', 'country' => 'EE', 'country_name' => 'Estonia'],
        ];
    }

    private static function finland(): array
    {
        return [
            ['bic' => 'NDEAFIHH', 'name' => 'Nordea Finland', 'country' => 'FI', 'country_name' => 'Finland'],
            ['bic' => 'OKOYFIHH', 'name' => 'OP Financial Group', 'country' => 'FI', 'country_name' => 'Finland'],
            ['bic' => 'DABAFIHH', 'name' => 'Danske Bank Finland', 'country' => 'FI', 'country_name' => 'Finland'],
            ['bic' => 'HELSFIHH', 'name' => 'Aktia Bank', 'country' => 'FI', 'country_name' => 'Finland'],
            ['bic' => 'SABORIFIHH', 'name' => 'S-Pankki', 'country' => 'FI', 'country_name' => 'Finland'],
        ];
    }

    private static function france(): array
    {
        return [
            ['bic' => 'BNPAFRPP', 'name' => 'BNP Paribas', 'country' => 'FR', 'country_name' => 'France'],
            ['bic' => 'SOGEFRPP', 'name' => 'Société Générale', 'country' => 'FR', 'country_name' => 'France'],
            ['bic' => 'CRLYFRPP', 'name' => 'Crédit Lyonnais (LCL)', 'country' => 'FR', 'country_name' => 'France'],
            ['bic' => 'AGRIFRPP', 'name' => 'Crédit Agricole', 'country' => 'FR', 'country_name' => 'France'],
            ['bic' => 'CEPAFRPP', 'name' => 'Caisse d\'Épargne', 'country' => 'FR', 'country_name' => 'France'],
            ['bic' => 'CMCIFRPP', 'name' => 'Crédit Mutuel', 'country' => 'FR', 'country_name' => 'France'],
            ['bic' => 'BPCEFRPP', 'name' => 'Banque Populaire', 'country' => 'FR', 'country_name' => 'France'],
            ['bic' => 'CCBPFRPP', 'name' => 'La Banque Postale', 'country' => 'FR', 'country_name' => 'France'],
        ];
    }

    private static function germany(): array
    {
        return [
            ['bic' => 'COBADEFF', 'name' => 'Commerzbank', 'country' => 'DE', 'country_name' => 'Germany'],
            ['bic' => 'DEUTDEFF', 'name' => 'Deutsche Bank', 'country' => 'DE', 'country_name' => 'Germany'],
            ['bic' => 'HYVEDEMM', 'name' => 'HypoVereinsbank (UniCredit)', 'country' => 'DE', 'country_name' => 'Germany'],
            ['bic' => 'INGDDEFF', 'name' => 'ING-DiBa', 'country' => 'DE', 'country_name' => 'Germany'],
            ['bic' => 'NOLADE21', 'name' => 'N26 Bank', 'country' => 'DE', 'country_name' => 'Germany'],
            ['bic' => 'BELADEBE', 'name' => 'Berliner Sparkasse', 'country' => 'DE', 'country_name' => 'Germany'],
            ['bic' => 'GENODEFF', 'name' => 'DZ Bank (Volksbank)', 'country' => 'DE', 'country_name' => 'Germany'],
            ['bic' => 'MARKDEF1', 'name' => 'Bundesbank', 'country' => 'DE', 'country_name' => 'Germany'],
        ];
    }

    private static function greece(): array
    {
        return [
            ['bic' => 'ETHNGRAA', 'name' => 'National Bank of Greece', 'country' => 'GR', 'country_name' => 'Greece'],
            ['bic' => 'PIABORGRAA', 'name' => 'Piraeus Bank', 'country' => 'GR', 'country_name' => 'Greece'],
            ['bic' => 'EFGBGRAA', 'name' => 'Eurobank Ergasias', 'country' => 'GR', 'country_name' => 'Greece'],
            ['bic' => 'CRBAGRAA', 'name' => 'Alpha Bank', 'country' => 'GR', 'country_name' => 'Greece'],
        ];
    }

    private static function hungary(): array
    {
        return [
            ['bic' => 'OTPVHUHB', 'name' => 'OTP Bank', 'country' => 'HU', 'country_name' => 'Hungary'],
            ['bic' => 'BABORHUHB', 'name' => 'Budapest Bank', 'country' => 'HU', 'country_name' => 'Hungary'],
            ['bic' => 'GIBAHUHB', 'name' => 'Erste Bank Hungary', 'country' => 'HU', 'country_name' => 'Hungary'],
            ['bic' => 'MABORHUHB', 'name' => 'K&H Bank (KBC)', 'country' => 'HU', 'country_name' => 'Hungary'],
            ['bic' => 'BACXHUHB', 'name' => 'UniCredit Bank Hungary', 'country' => 'HU', 'country_name' => 'Hungary'],
        ];
    }

    private static function ireland(): array
    {
        return [
            ['bic' => 'AABORIBKIE2D', 'name' => 'AIB Group', 'country' => 'IE', 'country_name' => 'Ireland'],
            ['bic' => 'BOFIIE2D', 'name' => 'Bank of Ireland', 'country' => 'IE', 'country_name' => 'Ireland'],
            ['bic' => 'PTSBIE2D', 'name' => 'Permanent TSB', 'country' => 'IE', 'country_name' => 'Ireland'],
            ['bic' => 'ABORNIE2D', 'name' => 'An Post Money', 'country' => 'IE', 'country_name' => 'Ireland'],
        ];
    }

    private static function italy(): array
    {
        return [
            ['bic' => 'BCITITMM', 'name' => 'Intesa Sanpaolo', 'country' => 'IT', 'country_name' => 'Italy'],
            ['bic' => 'UNCRITMM', 'name' => 'UniCredit', 'country' => 'IT', 'country_name' => 'Italy'],
            ['bic' => 'BPMOIT22', 'name' => 'Banco BPM', 'country' => 'IT', 'country_name' => 'Italy'],
            ['bic' => 'BABORPSITSP', 'name' => 'Banca Monte dei Paschi di Siena', 'country' => 'IT', 'country_name' => 'Italy'],
            ['bic' => 'ABORBNPAITMM', 'name' => 'BNL (BNP Paribas)', 'country' => 'IT', 'country_name' => 'Italy'],
            ['bic' => 'CRDIITMM', 'name' => 'Crédit Agricole Italia', 'country' => 'IT', 'country_name' => 'Italy'],
            ['bic' => 'SARDIT3S', 'name' => 'BPER Banca', 'country' => 'IT', 'country_name' => 'Italy'],
        ];
    }

    private static function latvia(): array
    {
        return [
            ['bic' => 'HABALV22', 'name' => 'Swedbank Latvia', 'country' => 'LV', 'country_name' => 'Latvia'],
            ['bic' => 'UNALABORLV2X', 'name' => 'SEB Latvia', 'country' => 'LV', 'country_name' => 'Latvia'],
            ['bic' => 'RIKOLV2X', 'name' => 'Luminor Bank Latvia', 'country' => 'LV', 'country_name' => 'Latvia'],
            ['bic' => 'CABORLV22', 'name' => 'Citadele banka', 'country' => 'LV', 'country_name' => 'Latvia'],
        ];
    }

    private static function lithuania(): array
    {
        return [
            ['bic' => 'HABALT22', 'name' => 'Swedbank Lithuania', 'country' => 'LT', 'country_name' => 'Lithuania'],
            ['bic' => 'CBVILT2X', 'name' => 'SEB Lithuania', 'country' => 'LT', 'country_name' => 'Lithuania'],
            ['bic' => 'AGBLLT2X', 'name' => 'Luminor Bank Lithuania', 'country' => 'LT', 'country_name' => 'Lithuania'],
            ['bic' => 'REVOLT21', 'name' => 'Revolut Bank UAB', 'country' => 'LT', 'country_name' => 'Lithuania'],
        ];
    }

    private static function luxembourg(): array
    {
        return [
            ['bic' => 'BCEELULL', 'name' => 'Banque et Caisse d\'Épargne', 'country' => 'LU', 'country_name' => 'Luxembourg'],
            ['bic' => 'BILABORLULL', 'name' => 'BIL (Banque Internationale)', 'country' => 'LU', 'country_name' => 'Luxembourg'],
            ['bic' => 'BGLLLULL', 'name' => 'BGL BNP Paribas', 'country' => 'LU', 'country_name' => 'Luxembourg'],
            ['bic' => 'CCRALULL', 'name' => 'Banque Raiffeisen', 'country' => 'LU', 'country_name' => 'Luxembourg'],
        ];
    }

    private static function malta(): array
    {
        return [
            ['bic' => 'VALLMTMT', 'name' => 'Bank of Valletta', 'country' => 'MT', 'country_name' => 'Malta'],
            ['bic' => 'HABORSMTMT', 'name' => 'HSBC Malta', 'country' => 'MT', 'country_name' => 'Malta'],
            ['bic' => 'APABORSMTMT', 'name' => 'APS Bank', 'country' => 'MT', 'country_name' => 'Malta'],
        ];
    }

    private static function netherlands(): array
    {
        return [
            ['bic' => 'RABONL2U', 'name' => 'Rabobank', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'INGBNL2A', 'name' => 'ING', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'ABNANL2A', 'name' => 'ABN AMRO', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'SNSBNL2A', 'name' => 'SNS Bank (de Volksbank)', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'ASNBNL21', 'name' => 'ASN Bank', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'BUNQNL2A', 'name' => 'bunq', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'TRIONL2U', 'name' => 'Triodos Bank', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'KNABNL2H', 'name' => 'Knab', 'country' => 'NL', 'country_name' => 'Netherlands'],
            ['bic' => 'RBRBNL21', 'name' => 'RegioBank', 'country' => 'NL', 'country_name' => 'Netherlands'],
        ];
    }

    private static function poland(): array
    {
        return [
            ['bic' => 'PKOPPLPW', 'name' => 'PKO Bank Polski', 'country' => 'PL', 'country_name' => 'Poland'],
            ['bic' => 'BREXPLPW', 'name' => 'mBank', 'country' => 'PL', 'country_name' => 'Poland'],
            ['bic' => 'INGBPLPW', 'name' => 'ING Bank Śląski', 'country' => 'PL', 'country_name' => 'Poland'],
            ['bic' => 'WBKPPLPP', 'name' => 'Santander Bank Polska', 'country' => 'PL', 'country_name' => 'Poland'],
            ['bic' => 'BIGBPLPW', 'name' => 'Bank Millennium', 'country' => 'PL', 'country_name' => 'Poland'],
            ['bic' => 'PPABPLPK', 'name' => 'BNP Paribas Bank Polska', 'country' => 'PL', 'country_name' => 'Poland'],
            ['bic' => 'ALBPPLPW', 'name' => 'Alior Bank', 'country' => 'PL', 'country_name' => 'Poland'],
        ];
    }

    private static function portugal(): array
    {
        return [
            ['bic' => 'CGDIPTPL', 'name' => 'Caixa Geral de Depósitos', 'country' => 'PT', 'country_name' => 'Portugal'],
            ['bic' => 'BCOMPTPL', 'name' => 'Millennium BCP', 'country' => 'PT', 'country_name' => 'Portugal'],
            ['bic' => 'TOTAPTPL', 'name' => 'Santander Totta', 'country' => 'PT', 'country_name' => 'Portugal'],
            ['bic' => 'BPIPPTPL', 'name' => 'Banco BPI (CaixaBank)', 'country' => 'PT', 'country_name' => 'Portugal'],
            ['bic' => 'CCCMPTPL', 'name' => 'Crédito Agrícola', 'country' => 'PT', 'country_name' => 'Portugal'],
        ];
    }

    private static function romania(): array
    {
        return [
            ['bic' => 'BTRLRO22', 'name' => 'Banca Transilvania', 'country' => 'RO', 'country_name' => 'Romania'],
            ['bic' => 'RNCBROBU', 'name' => 'BCR (Erste Group)', 'country' => 'RO', 'country_name' => 'Romania'],
            ['bic' => 'BRDEROBUP', 'name' => 'BRD (Société Générale)', 'country' => 'RO', 'country_name' => 'Romania'],
            ['bic' => 'INGBROBU', 'name' => 'ING Bank Romania', 'country' => 'RO', 'country_name' => 'Romania'],
            ['bic' => 'BACXROBU', 'name' => 'UniCredit Bank Romania', 'country' => 'RO', 'country_name' => 'Romania'],
            ['bic' => 'RZBRROBJ', 'name' => 'Raiffeisen Bank Romania', 'country' => 'RO', 'country_name' => 'Romania'],
        ];
    }

    private static function slovakia(): array
    {
        return [
            ['bic' => 'TATRSKBX', 'name' => 'Tatra banka', 'country' => 'SK', 'country_name' => 'Slovakia'],
            ['bic' => 'GIBASKBX', 'name' => 'Slovenská sporiteľňa (Erste)', 'country' => 'SK', 'country_name' => 'Slovakia'],
            ['bic' => 'SUBASKBX', 'name' => 'VÚB banka (Intesa)', 'country' => 'SK', 'country_name' => 'Slovakia'],
            ['bic' => 'CABORSKBX', 'name' => 'ČSOB Slovakia', 'country' => 'SK', 'country_name' => 'Slovakia'],
        ];
    }

    private static function slovenia(): array
    {
        return [
            ['bic' => 'LJBASI2X', 'name' => 'NLB (Nova Ljubljanska banka)', 'country' => 'SI', 'country_name' => 'Slovenia'],
            ['bic' => 'NKBMSI2X', 'name' => 'Nova KBM (OTP)', 'country' => 'SI', 'country_name' => 'Slovenia'],
            ['bic' => 'SKBASI2X', 'name' => 'SKB banka (OTP)', 'country' => 'SI', 'country_name' => 'Slovenia'],
            ['bic' => 'BACXSI22', 'name' => 'UniCredit Banka Slovenija', 'country' => 'SI', 'country_name' => 'Slovenia'],
        ];
    }

    private static function spain(): array
    {
        return [
            ['bic' => 'CAIXESBB', 'name' => 'CaixaBank', 'country' => 'ES', 'country_name' => 'Spain'],
            ['bic' => 'BSCHESMMXXX', 'name' => 'Banco Santander', 'country' => 'ES', 'country_name' => 'Spain'],
            ['bic' => 'BBVAESMMXXX', 'name' => 'BBVA', 'country' => 'ES', 'country_name' => 'Spain'],
            ['bic' => 'SABORESMMXXX', 'name' => 'Banco Sabadell', 'country' => 'ES', 'country_name' => 'Spain'],
            ['bic' => 'CAABORHBESM', 'name' => 'Bankinter', 'country' => 'ES', 'country_name' => 'Spain'],
            ['bic' => 'UCJAES2M', 'name' => 'Unicaja Banco', 'country' => 'ES', 'country_name' => 'Spain'],
            ['bic' => 'BSABESBB', 'name' => 'Banco Mediolanum', 'country' => 'ES', 'country_name' => 'Spain'],
        ];
    }

    private static function sweden(): array
    {
        return [
            ['bic' => 'NDEASESS', 'name' => 'Nordea Sweden', 'country' => 'SE', 'country_name' => 'Sweden'],
            ['bic' => 'HANDSESS', 'name' => 'Handelsbanken', 'country' => 'SE', 'country_name' => 'Sweden'],
            ['bic' => 'SWEDSESS', 'name' => 'Swedbank', 'country' => 'SE', 'country_name' => 'Sweden'],
            ['bic' => 'ESSESESS', 'name' => 'SEB', 'country' => 'SE', 'country_name' => 'Sweden'],
            ['bic' => 'DAABORSEGG', 'name' => 'Danske Bank Sweden', 'country' => 'SE', 'country_name' => 'Sweden'],
            ['bic' => 'ELLFSESS', 'name' => 'Länsförsäkringar Bank', 'country' => 'SE', 'country_name' => 'Sweden'],
        ];
    }

    // ── EEA (non-EU) ────────────────────────────────────

    private static function norway(): array
    {
        return [
            ['bic' => 'DNBANOKKXXX', 'name' => 'DNB ASA', 'country' => 'NO', 'country_name' => 'Norway'],
            ['bic' => 'NDEANOKK', 'name' => 'Nordea Norway', 'country' => 'NO', 'country_name' => 'Norway'],
            ['bic' => 'SHEDNO22', 'name' => 'SpareBank 1', 'country' => 'NO', 'country_name' => 'Norway'],
            ['bic' => 'HANDNOKK', 'name' => 'Handelsbanken Norway', 'country' => 'NO', 'country_name' => 'Norway'],
        ];
    }

    private static function iceland(): array
    {
        return [
            ['bic' => 'LABABORISHH', 'name' => 'Landsbankinn', 'country' => 'IS', 'country_name' => 'Iceland'],
            ['bic' => 'ABORISHH', 'name' => 'Arion banki', 'country' => 'IS', 'country_name' => 'Iceland'],
            ['bic' => 'ISIBISHH', 'name' => 'Íslandsbanki', 'country' => 'IS', 'country_name' => 'Iceland'],
        ];
    }

    private static function liechtenstein(): array
    {
        return [
            ['bic' => 'BALPLI22', 'name' => 'LGT Bank', 'country' => 'LI', 'country_name' => 'Liechtenstein'],
            ['bic' => 'VPBRLI2X', 'name' => 'VP Bank', 'country' => 'LI', 'country_name' => 'Liechtenstein'],
        ];
    }

    // ── Pan-European digital banks ──────────────────────

    private static function panEuropean(): array
    {
        return [
            ['bic' => 'REVOLT21', 'name' => 'Revolut', 'country' => 'LT', 'country_name' => 'Pan-EU (Lithuania license)'],
            ['bic' => 'NTSBDEB1', 'name' => 'N26', 'country' => 'DE', 'country_name' => 'Pan-EU (Germany license)'],
            ['bic' => 'BUNQNL2A', 'name' => 'bunq', 'country' => 'NL', 'country_name' => 'Pan-EU (Netherlands license)'],
            ['bic' => 'TRWIBEB1', 'name' => 'Wise (TransferWise)', 'country' => 'BE', 'country_name' => 'Pan-EU (Belgium license)'],
        ];
    }
}
