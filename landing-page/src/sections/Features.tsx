import {
  Container,
  SimpleGrid,
  Card,
  Text,
  Title,
  ThemeIcon,
  Stack,
} from '@mantine/core';
import {
  IconNfc,
  IconBuildingBank,
  IconShieldLock,
  IconFingerprint,
  IconArrowsExchange,
  IconCoin,
} from '@tabler/icons-react';
import { features } from '../data/features';

const iconMap: Record<string, typeof IconNfc> = {
  nfc: IconNfc,
  bank: IconBuildingBank,
  shield: IconShieldLock,
  fingerprint: IconFingerprint,
  transfer: IconArrowsExchange,
  euro: IconCoin,
};

export function Features() {
  return (
    <Container size="lg" py={80}>
      <Stack align="center" gap="lg" mb={48}>
        <Title order={2} ta="center">
          Built for Europe
        </Title>
        <Text size="lg" c="dimmed" ta="center" maw={540}>
          Everything you need for contactless payments — owned by a European
          foundation, not a US tech company.
        </Text>
      </Stack>
      <SimpleGrid cols={{ base: 1, sm: 2, md: 3 }} spacing="lg">
        {features.map((feature) => {
          const Icon = iconMap[feature.icon];
          return (
            <Card key={feature.icon} padding="lg" radius="md" withBorder>
              <ThemeIcon size={48} radius="md" variant="light" mb="md">
                <Icon size={24} />
              </ThemeIcon>
              <Text fw={600} size="lg" mb="xs">
                {feature.title}
              </Text>
              <Text size="sm" c="dimmed">
                {feature.description}
              </Text>
            </Card>
          );
        })}
      </SimpleGrid>
    </Container>
  );
}
