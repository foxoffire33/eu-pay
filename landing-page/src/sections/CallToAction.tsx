import {
  Box,
  Container,
  Title,
  Text,
  Button,
  Group,
  Stack,
} from '@mantine/core';
import { IconDownload, IconBrandGithub } from '@tabler/icons-react';

export function CallToAction() {
  return (
    <Box
      py={80}
      style={{
        background:
          'linear-gradient(135deg, var(--mantine-color-eupay-6) 0%, var(--mantine-color-eupay-8) 100%)',
      }}
    >
      <Container size="lg">
        <Stack align="center" gap="lg" ta="center">
          <Title order={2} c="white" size={36}>
            European Payments, European Ownership
          </Title>
          <Text size="lg" c="gray.3" maw={560}>
            Stichting EU Pay is a Dutch foundation. No VC funding. No data
            monetization. Just open-source payment infrastructure for 450
            million Europeans.
          </Text>
          <Group mt="md">
            <Button
              component="a"
              href="https://github.com/user/eu-pay/releases"
              size="lg"
              variant="white"
              color="dark"
              leftSection={<IconDownload size={20} />}
            >
              Download EU Pay
            </Button>
            <Button
              component="a"
              href="https://github.com/user/eu-pay"
              size="lg"
              variant="outline"
              color="white"
              leftSection={<IconBrandGithub size={20} />}
            >
              Star on GitHub
            </Button>
          </Group>
        </Stack>
      </Container>
    </Box>
  );
}
