import {
  Box,
  Container,
  Title,
  Text,
  Button,
  Group,
  Stack,
} from '@mantine/core';
import { IconBrandGithub, IconDownload } from '@tabler/icons-react';

export function Hero() {
  return (
    <Box
      py={80}
      style={{
        background: 'linear-gradient(135deg, var(--mantine-color-eupay-0) 0%, var(--mantine-color-eupay-1) 100%)',
      }}
    >
      <Container size="lg">
        <Stack align="center" gap="lg" ta="center" maw={720} mx="auto">
          <Title order={1} size={48} lh={1.2}>
            Tap to Pay with{' '}
            <Text span inherit c="eupay.6">
              European Infrastructure
            </Text>
          </Title>
          <Text size="xl" c="dimmed" maw={560}>
            NFC payments without Google Pay or Apple Pay. Open source.
            Zero-knowledge encryption. Digital Euro ready.
          </Text>
          <Group mt="md">
            <Button
              component="a"
              href="https://github.com/user/eu-pay/releases"
              size="lg"
              leftSection={<IconDownload size={20} />}
            >
              Get the App
            </Button>
            <Button
              component="a"
              href="https://github.com/user/eu-pay"
              size="lg"
              variant="outline"
              leftSection={<IconBrandGithub size={20} />}
            >
              View on GitHub
            </Button>
          </Group>
        </Stack>
      </Container>
    </Box>
  );
}
