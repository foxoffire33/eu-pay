import {
  Container,
  Title,
  Text,
  Tabs,
  Badge,
  Group,
  Stack,
} from '@mantine/core';
import { bankData } from '../data/banks';

export function SupportedBanks() {
  return (
    <Container size="lg" py={80}>
      <Stack align="center" gap="lg" mb={48}>
        <Title order={2} ta="center">
          140+ Banks Across 30 EU/EEA Countries
        </Title>
        <Text size="lg" c="dimmed" ta="center" maw={540}>
          PSD2 is mandatory for every licensed bank in the EU. Top up or send
          money from your existing bank account.
        </Text>
      </Stack>
      <Tabs defaultValue={bankData[0].country} variant="pills">
        <Tabs.List justify="center" mb="xl">
          {bankData.map((country) => (
            <Tabs.Tab key={country.country} value={country.country}>
              {country.flag} {country.country}
            </Tabs.Tab>
          ))}
          <Tabs.Tab value="more" disabled>
            + 22 more
          </Tabs.Tab>
        </Tabs.List>
        {bankData.map((country) => (
          <Tabs.Panel key={country.country} value={country.country}>
            <Group justify="center" gap="sm">
              {country.banks.map((bank) => (
                <Badge
                  key={bank}
                  size="lg"
                  variant="light"
                  radius="sm"
                >
                  {bank}
                </Badge>
              ))}
            </Group>
          </Tabs.Panel>
        ))}
      </Tabs>
    </Container>
  );
}
