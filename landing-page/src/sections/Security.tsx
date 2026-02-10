import {
  Box,
  Container,
  Title,
  Text,
  List,
  ThemeIcon,
  Stack,
  SimpleGrid,
} from '@mantine/core';
import {
  IconLock,
  IconEyeOff,
  IconCode,
  IconBuildingCommunity,
  IconShieldCheck,
} from '@tabler/icons-react';

const points = [
  {
    icon: IconLock,
    title: 'Zero-Knowledge Encryption',
    description:
      'RSA-4096 OAEP + AES-256-GCM envelope encryption. Your private key never leaves your device (Android Keystore).',
  },
  {
    icon: IconEyeOff,
    title: 'No Tracking',
    description:
      'No cookies, no analytics, no device fingerprinting, no ad IDs. We cannot monetize your data because we cannot read it.',
  },
  {
    icon: IconCode,
    title: 'Open Source',
    description:
      'Licensed under EUPL-1.2 (European Union Public Licence). Every line of code is auditable.',
  },
  {
    icon: IconBuildingCommunity,
    title: 'EU Jurisdiction',
    description:
      'Stichting EU Pay — a Dutch foundation registered at KVK. No VC funding, no shareholders, no data sales.',
  },
  {
    icon: IconShieldCheck,
    title: 'GDPR by Design',
    description:
      'Art. 25 data protection by design. Blind indexes for search, encrypted blobs for storage, right to erasure built in.',
  },
];

export function Security() {
  return (
    <Box py={80} bg="gray.0">
      <Container size="lg">
        <SimpleGrid cols={{ base: 1, md: 2 }} spacing={48}>
          <Stack>
            <Title order={2}>Privacy Is Not a Feature</Title>
            <Text size="lg" c="dimmed">
              EU Pay is built so that even we cannot access your personal data.
              The server stores only encrypted blobs and blind indexes — never plaintext.
            </Text>
          </Stack>
          <List spacing="lg" size="sm">
            {points.map((point) => (
              <List.Item
                key={point.title}
                icon={
                  <ThemeIcon size={32} radius="xl" variant="light">
                    <point.icon size={18} />
                  </ThemeIcon>
                }
              >
                <Text fw={600}>{point.title}</Text>
                <Text size="sm" c="dimmed">
                  {point.description}
                </Text>
              </List.Item>
            ))}
          </List>
        </SimpleGrid>
      </Container>
    </Box>
  );
}
